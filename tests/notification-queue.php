<?php
/** Included only by the disposable WordPress integration suite. */
if ( ! defined( 'SSC_TEST_SITE' ) || ! SSC_TEST_SITE || ! function_exists( 'check' ) ) { exit( 1 ); }
global $wpdb;
$queue_table = SSC_Notification_Queue::table();
$job_id = SSC_Notification_Queue::enqueue( 'email', $case_id );
check( $job_id > 0 && SSC_Notification_Queue::enqueue( 'email', $case_id ) === $job_id, 'Durable queue deduplicates a submission/channel pair' );
$job = SSC_Notification_Queue::claim( $job_id );
check( $job && ! SSC_Notification_Queue::claim( $job_id ), 'Only one worker can claim a current notification lease' );
$other_id = SSC_Notification_Queue::enqueue( 'messenger', $case_id );
SSC_Notification_Queue::finish( $job, false, 'secret-token and medical narrative must not persist' );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$queue_table} WHERE id = %d", $job_id ), ARRAY_A );
check( $row['status'] === 'pending' && (int) $row['next_at'] > time() && $row['last_error'] === 'delivery-failed', 'Failed delivery uses backoff and redacts provider details' );
check( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$queue_table} WHERE id = %d", $other_id ) ) === 1, 'Finishing one delivery preserves a concurrently enqueued job' );
check( ! SSC_Notification_Queue::finish( $job, true ), 'An obsolete worker cannot overwrite a finished lease' );
for ( $attempt = 1; $attempt < 5; ++$attempt ) {
    $wpdb->update( $queue_table, array( 'next_at' => 0 ), array( 'id' => $job_id ) );
    SSC_Notification_Queue::finish( SSC_Notification_Queue::claim( $job_id ), false, 'test' );
}
check( SSC_Notification_Queue::failed_count() > 0 && ! SSC_Notification_Queue::claim( $job_id ), 'Five failed attempts remain visible without an endless retry loop' );
SSC_Notification_Queue::retry_exhausted();
$job = SSC_Notification_Queue::claim( $job_id );
check( $job && (int) $job['attempts'] === 0, 'Explicit retry reopens exhausted jobs' );
$wpdb->update( $queue_table, array( 'locked_until' => time() - 1 ), array( 'id' => $job_id ) );
$replacement = SSC_Notification_Queue::claim( $job_id );
check( $replacement && $replacement['lease'] !== $job['lease'] && ! SSC_Notification_Queue::finish( $job, true ), 'Expired worker leases recover without accepting stale results' );
SSC_Notification_Queue::finish( $replacement, true );
$wpdb->delete( $queue_table, array( 'submission_id' => $case_id ) );

update_option( 'ssc_notify_queue', array( array( 'submission_id' => $case_id, 'channel' => 'email', 'attempts' => 5, 'next_at' => 0, 'error' => 'sensitive legacy detail' ) ), false );
SSC_Notification_Queue::migrate_legacy();
check( get_option( 'ssc_notify_queue', null ) === null && SSC_Notification_Queue::last_failure()['attempts'] == 5, 'Legacy jobs migrate without losing exhausted failures' );
$wpdb->delete( $queue_table, array( 'submission_id' => $case_id ) );

// A malformed legacy entry used to abort the loop, stranding every job behind it.
update_option(
    'ssc_notify_queue',
    array(
        'not-an-array',
        array( 'channel' => 'email' ),
        array( 'submission_id' => $case_id, 'channel' => 'messenger', 'attempts' => 1, 'next_at' => 0 ),
    ),
    false
);
SSC_Notification_Queue::migrate_legacy();
$migrated = $wpdb->get_col( $wpdb->prepare( "SELECT channel FROM {$queue_table} WHERE submission_id = %d", $case_id ) );
check( in_array( 'messenger', $migrated, true ), 'A malformed legacy entry does not strand the jobs queued after it' );
check( get_option( 'ssc_notify_queue', null ) === null, 'Skipped legacy entries still clear the migrated option' );
$wpdb->delete( $queue_table, array( 'submission_id' => $case_id ) );

$saved_modules = SSC_Modules::active_ids();
update_option( SSC_Modules::OPTION, array( 'notifications', 'pharma' ) );
update_option( 'ssc_pharma_setup', array( 'done' => 1, 'pv_contact' => 'pv@example.invalid' ) );
$mail_count = 0; $mail_to = '';
$capture_mail = function ( $pre, $atts ) use ( &$mail_count, &$mail_to ) { ++$mail_count; $mail_to = $atts['to']; return true; };
add_filter( 'pre_wp_mail', $capture_mail, 30, 2 );
$notifications = new SSC_Module_Notifications();
$notifications->dispatch_for_submission( $case_id, 'pharma_adr' );
$notifications->dispatch_for_submission( $case_id, 'pharma_adr' );
check( $mail_count === 1 && $mail_to === 'pv@example.invalid', 'A repeated dispatch sends once to the configured PV inbox (mail mocked)' );
remove_filter( 'pre_wp_mail', $capture_mail, 30 );
$job_id = SSC_Notification_Queue::enqueue( 'messenger', $case_id );
update_option( SSC_Modules::OPTION, array() );
check( SSC_Module_Notifications::retry_failed() === 0 && in_array( (string) $job_id, array_map( 'strval', SSC_Notification_Queue::due_ids() ), true ), 'Disabled notifications preserve pending work without sending' );
update_option( SSC_Modules::OPTION, $saved_modules );
$wpdb->delete( $queue_table, array( 'submission_id' => $case_id ) );
check( wp_get_schedule( SSC_Cron::RETRY_HOOK ) === 'ssc_five_minutes', 'Notification retries have a dedicated five-minute schedule' );
