<?php
namespace Catlaq\Expo\Helpers;

/**
 * Map plugin-specific capabilities to the minimum core capability
 * a role must already provide before inheriting the Catlaq permission.
 *
 * @return array<string,string>
 */
function get_capabilities(): array {
    return [
        'manage_catlaq_ai'          => 'manage_options',
        'manage_catlaq_digital_expo' => 'manage_options',
        'manage_catlaq_agreements'  => 'edit_pages',
        'manage_catlaq_expo'        => 'edit_pages',
        'view_catlaq_reports'       => 'read',
    ];
}
