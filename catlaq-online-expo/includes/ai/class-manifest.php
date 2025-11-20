<?php
namespace Catlaq\Expo\AI;

class Manifest {
    public static function get(): array {
        return [
            'orchestrator' => [
                'name'        => 'Catlaq Core Intelligence',
                'persona'     => 'Fair, commission-free B2B advisor.',
                'responsibilities' => [
                    'Collect role/plan/quota context.',
                    'Assign the right specialist agent.',
                    'Validate agent outputs before responding.',
                    'Trigger security workflows for suspicious behavior.',
                    'Produce encrypted audit logs.',
                ],
                'constraints' => [
                    'Speak in the user\'s language.',
                    'Provide guidance, not financial/legal advice.',
                    'Always remind that Catlaq takes no commission.',
                ],
            ],
            'agents' => [
                'membership_coach' => [
                    'label'       => 'Membership Coach',
                    'scope'       => 'Plan recommendations, quota usage, onboarding.',
                    'inputs'      => [ 'profile', 'membership', 'usage' ],
                    'output_json' => [ 'suggestions', 'quota_status' ],
                ],
                'agreement_facilitator' => [
                    'label'       => 'Agreement Facilitator',
                    'scope'       => 'Agreement rooms, documents, dispute flow.',
                    'inputs'      => [ 'room', 'messages', 'documents' ],
                    'output_json' => [ 'next_action', 'message_template' ],
                ],
                'expo_concierge' => [
                    'label'       => 'Digital Expo Concierge',
                    'scope'       => 'Booth inventory, RFQ matching, showcase tips.',
                    'inputs'      => [ 'products', 'rfq', 'company' ],
                    'output_json' => [ 'listings', 'offers' ],
                ],
                'logistics_payments' => [
                    'label'       => 'Logistics & Payments Sentinel',
                    'scope'       => 'Shipment milestones and payment states.',
                    'inputs'      => [ 'shipments', 'payments' ],
                    'output_json' => [ 'status_summary', 'alerts' ],
                ],
                'broker_ops' => [
                    'label'       => 'Broker Ops Bot',
                    'scope'       => 'Freelance broker assignments, commission tracking.',
                    'inputs'      => [ 'assignments', 'commissions' ],
                    'output_json' => [ 'tasks', 'compliance' ],
                ],
                'security_guardian' => [
                    'label'       => 'Security Guardian',
                    'scope'       => 'File hashes and REST anomaly detection.',
                    'inputs'      => [ 'file_hash', 'audit_logs' ],
                    'output_json' => [ 'severity', 'action' ],
                ],
            ],
            'logging' => [
                'table'     => 'catlaq_ai_logs',
                'encryption'=> 'aes-256-gcm',
            ],
        ];
    }

    public static function get_agent( string $slug ): ?array {
        $manifest = self::get();
        return $manifest['agents'][ $slug ] ?? null;
    }
}
