<?php
namespace Catlaq\Expo\REST;

use Catlaq\Expo\Modules\Payments\Payment_Service;
use Catlaq\Expo\Schema;

class Rest_Controller {
    public function register(): void {
        ( new Profiles_Controller() )->register();
        ( new RFQ_Controller() )->register();
        ( new Agreements_Controller() )->register();
        ( new Orders_Controller() )->register();
        ( new Logistics_Controller() )->register();
        ( new Documents_Controller() )->register();
        ( new Payments_Controller( new Payment_Service() ) )->register();
        ( new Engagement_Controller() )->register();
        ( new Membership_Controller() )->register();

        add_action(
            'rest_api_init',
            function () {
                register_rest_route(
                    'catlaq/v1',
                    '/status',
                    [
                        'methods'             => 'GET',
                        'callback'            => [ $this, 'status' ],
                        'permission_callback' => '__return_true',
                    ]
                );
            }
        );
    }

    public function status(): array {
        global $wpdb;

        $tables          = Schema::table_names();
        $missing_tables  = [];
        foreach ( $tables as $table ) {
            $table_name = $wpdb->prefix . $table;
            $exists     = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
            if ( $exists !== $table_name ) {
                $missing_tables[] = $table_name;
            }
        }

        return [
            'version'        => CATLAQ_PLUGIN_VERSION,
            'timestamp'      => time(),
            'missing_tables' => $missing_tables,
            'status'         => empty( $missing_tables ) ? 'ok' : 'install_required',
        ];
    }
}
