<?php
namespace Catlaq\Expo;

use Catlaq\Expo\Modules\AI\AI_Manifest;
use WP_Error;

class AI_Kernel {
    private static $instance;

    /**
     * @var AI_Manifest
     */
    private $manifest;

    /**
     * @var array<string,mixed>
     */
    private $config;

    private function __construct( ?AI_Manifest $manifest = null ) {
        $this->manifest = $manifest ?: new AI_Manifest();
        $this->config   = $this->bootstrap_config();
    }

    public static function instance( ?AI_Manifest $manifest = null ): self {
        if ( null === self::$instance ) {
            self::$instance = new self( $manifest );
        }
        return self::$instance;
    }

    public function manifest(): AI_Manifest {
        return $this->manifest;
    }

    public function refresh_manifest(): void {
        $this->manifest = new AI_Manifest();
    }

    public function moderate( array $payload ): array {
        $content = (string) ( $payload['content'] ?? '' );
        $flags   = array();

        foreach ( $this->manifest->moderation_rules() as $rule ) {
            $keyword = $rule['keyword'] ?? '';
            if ( '' === $keyword ) {
                continue;
            }
            if ( false !== stripos( $content, $keyword ) ) {
                $flags[] = array(
                    'keyword' => $keyword,
                    'label'   => $rule['label'] ?? $keyword,
                );
            }
        }

        $status = empty( $flags ) ? 'approved' : 'flagged';

        $result = array(
            'status'      => $status,
            'flags'       => $flags,
            'flag_count'  => count( $flags ),
            'max_allowed' => (int) $this->config['moderation']['max_flags_before_block'],
        );

        /**
         * Fires after the AI moderation layer has evaluated content.
         */
        do_action( 'catlaq_ai_moderated', $result, $payload );

        return $result;
    }

    public function score( array $signals ): array {
        $score   = $this->manifest->score_base();
        $weights = $this->manifest->score_weights();

        foreach ( $signals as $key => $value ) {
            if ( ! isset( $weights[ $key ] ) ) {
                continue;
            }
            $score += (int) $weights[ $key ] * (int) $value;
        }

        $score = max( 0, min( 100, $score ) );

        $result = array(
            'score'   => $score,
            'signals' => $signals,
        );

        do_action( 'catlaq_ai_scored', $result );

        return $result;
    }

    public function summarize( array $payload ): array {
        $template  = $this->manifest->summary_template();
        $topic     = $payload['topic'] ?? 'Unknown';
        $mood      = $payload['mood'] ?? 'neutral';
        $confidence = (int) ( $payload['confidence'] ?? 70 );

        $summary = strtr(
            $template,
            array(
                '{{topic}}'      => $topic,
                '{{mood}}'       => $mood,
                '{{confidence}}' => (string) $confidence,
            )
        );

        $result = array(
            'summary'    => $summary,
            'topic'      => $topic,
            'mood'       => $mood,
            'confidence' => $confidence,
        );

        do_action( 'catlaq_ai_summarized', $result, $payload );

        return $result;
    }

    private function bootstrap_config(): array {
        $defaults = array(
            'moderation' => array(
                'max_flags_before_block' => 3,
            ),
            'runtime'    => array(
                'provider'   => get_option( 'catlaq_ai_provider', 'local_http' ),
                'endpoint'   => get_option( 'catlaq_ai_endpoint', 'http://127.0.0.1:11434/api/generate' ),
                'model_name' => get_option( 'catlaq_ai_model_name', 'mistral' ),
                'model_path' => get_option( 'catlaq_ai_model_path', '' ),
            ),
        );

        return (array) apply_filters( 'catlaq_ai_config', $defaults );
    }

    public function runtime_config(): array {
        return (array) $this->config['runtime'];
    }

    /**
     * Send prompt to configured runtime and return output.
     *
     * @param array $request {
     *   @type string $prompt
     *   @type string $system
     *   @type int    $max_tokens
     *   @type float  $temperature
     * }
     *
     * @return array|WP_Error
     */
    public function generate( array $request ) {
        $runtime = $this->runtime_config();
        if ( ( $runtime['provider'] ?? 'disabled' ) === 'disabled' ) {
            return new WP_Error( 'catlaq_ai_provider_disabled', __( 'AI runtime is disabled.', 'catlaq-online-expo' ) );
        }

        $prompt = trim( (string) ( $request['prompt'] ?? '' ) );
        if ( '' === $prompt ) {
            return new WP_Error( 'catlaq_ai_prompt', __( 'Prompt is required.', 'catlaq-online-expo' ) );
        }

        switch ( $runtime['provider'] ) {
            case 'local_http':
            default:
                return $this->call_local_http_runtime( $runtime, $request );
        }
    }

    private function call_local_http_runtime( array $runtime, array $request ) {
        $endpoint = $runtime['endpoint'] ?? '';
        if ( '' === $endpoint ) {
            return new WP_Error( 'catlaq_ai_endpoint', __( 'HTTP endpoint is not configured.', 'catlaq-online-expo' ) );
        }

        $body = array(
            'model'  => $runtime['model_name'] ?? 'mistral',
            'prompt' => $request['prompt'],
            'stream' => false,
        );

        $options = array();
        if ( isset( $request['max_tokens'] ) ) {
            $options['num_predict'] = (int) $request['max_tokens'];
        }
        if ( isset( $request['temperature'] ) ) {
            $options['temperature'] = (float) $request['temperature'];
        }
        if ( ! empty( $options ) ) {
            $body['options'] = $options;
        }
        if ( ! empty( $request['system'] ) ) {
            $body['system'] = $request['system'];
        }

        $response = wp_remote_post(
            $endpoint,
            array(
                'headers' => array( 'Content-Type' => 'application/json' ),
                'body'    => wp_json_encode( $body ),
                'timeout' => 60,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code || ! is_array( $data ) ) {
            return new WP_Error( 'catlaq_ai_http', __( 'Invalid response from AI runtime.', 'catlaq-online-expo' ), array( 'body' => $data, 'code' => $code ) );
        }

        $output = '';
        if ( isset( $data['response'] ) ) {
            $output = (string) $data['response'];
        } elseif ( isset( $data['choices'][0]['message']['content'] ) ) {
            $output = (string) $data['choices'][0]['message']['content'];
        }

        return array(
            'output' => $output,
            'raw'    => $data,
        );
    }
}
