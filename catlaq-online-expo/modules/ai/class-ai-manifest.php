<?php
namespace Catlaq\Expo\Modules\AI;

/**
 * Loads static AI manifest JSON files and exposes helper accessors.
 */
class AI_Manifest {
	/**
	 * Cached manifest payload.
	 *
	 * @var array<string,mixed>
	 */
	private $cache = array();

	/**
	 * Base directory for manifest files.
	 *
	 * @var string
	 */
	private $directory;

	public function __construct( ?string $directory = null ) {
		$this->directory = $directory ?: CATLAQ_PLUGIN_PATH . 'modules/ai/manifests/';
	}

	/**
	 * Load and merge every JSON manifest in the directory.
	 */
	public function load_schema(): array {
		if ( ! empty( $this->cache ) ) {
			return $this->cache;
		}

		$payload = array();
		if ( ! is_dir( $this->directory ) ) {
			$this->cache = $payload;
			return $payload;
		}

		$files = glob( trailingslashit( $this->directory ) . '*.json' );
		foreach ( $files as $file ) {
			$contents = file_get_contents( $file );
			if ( false === $contents ) {
				continue;
			}
			$decoded = json_decode( $contents, true );
			if ( null === $decoded ) {
				continue;
			}

			$payload = array_merge_recursive( $payload, $decoded );
		}

		$this->cache = $payload;
		return $this->cache;
	}

	public function moderation_rules(): array {
		$schema = $this->load_schema();
		return $schema['moderation']['keywords'] ?? array();
	}

	public function score_weights(): array {
		$schema = $this->load_schema();
		return $schema['scores']['weights'] ?? array();
	}

	public function score_base(): int {
		$schema = $this->load_schema();
		return (int) ( $schema['scores']['base'] ?? 70 );
	}

	public function summary_template(): string {
		$schema = $this->load_schema();
		return (string) ( $schema['templates']['summary'] ?? 'Summary: {{topic}} (mood: {{mood}})' );
	}
}
