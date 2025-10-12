<?php
declare(strict_types=1);

namespace Transfers\Lib;

use PDO;

final class Log
{
    public static function audit(PDO $pdo, array $row): void
    {
        $sql = "INSERT INTO transfer_audit_log
          (entity_type, entity_pk, transfer_pk, transfer_id, vend_consignment_id, vend_transfer_id,
           action, status, actor_type, actor_id, outlet_from, outlet_to, data_before, data_after,
           metadata, error_details, processing_time_ms, api_response, session_id, ip_address, user_agent, created_at)
          VALUES
          (:entity_type, :entity_pk, :transfer_pk, :transfer_id, :vend_consignment_id, :vend_transfer_id,
           :action, :status, :actor_type, :actor_id, :outlet_from, :outlet_to, :data_before, :data_after,
           :metadata, :error_details, :processing_time_ms, :api_response, :session_id, :ip_address, :user_agent, NOW())";
        $pdo->prepare($sql)->execute($row + [
            'entity_type'=>'transfer','status'=>'success','actor_type'=>'user','actor_id'=>(string)Security::currentUserId(),
            'data_before'=>null,'data_after'=>null,'metadata'=>null,'error_details'=>null,'processing_time_ms'=>null,
            'api_response'=>null,'session_id'=>session_id(),'ip_address'=>$_SERVER['REMOTE_ADDR'] ?? null,'user_agent'=>$_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }

    public static function unified(PDO $pdo, array $row): void
    {
        $sql = "INSERT INTO transfer_unified_log
          (trace_id, correlation_id, category, event_type, severity, message, transfer_id, shipment_id, parcel_id, item_id,
           vend_consignment_id, vend_transfer_id, ai_decision_id, ai_model_version, ai_confidence, actor_user_id, actor_role,
           actor_ip, event_data, context_data, tags, duration_ms, memory_mb, api_latency_ms, db_query_ms, source_system, environment,
           server_name, php_version, created_at, event_timestamp)
          VALUES
          (:trace_id, :correlation_id, :category, :event_type, :severity, :message, :transfer_id, :shipment_id, :parcel_id, :item_id,
           :vend_consignment_id, :vend_transfer_id, :ai_decision_id, :ai_model_version, :ai_confidence, :actor_user_id, :actor_role,
           :actor_ip, :event_data, :context_data, :tags, :duration_ms, :memory_mb, :api_latency_ms, :db_query_ms, 'CIS', 'production',
           :server_name, :php_version, NOW(), NOW())";
        $pdo->prepare($sql)->execute($row + [
            'severity'=>'info','category'=>'transfer','event_type'=>'PACK','message'=>'',
            'trace_id'=>bin2hex(random_bytes(8)), 'correlation_id'=>null,
            'ai_decision_id'=>null,'ai_model_version'=>null,'ai_confidence'=>null,
            'actor_user_id'=>Security::currentUserId(),'actor_role'=>null,
            'actor_ip'=>$_SERVER['REMOTE_ADDR']??null,'event_data'=>null,'context_data'=>null,'tags'=>json_encode(['ui']),
            'duration_ms'=>null,'memory_mb'=>null,'api_latency_ms'=>null,'db_query_ms'=>null,
            'server_name'=>gethostname(),'php_version'=>PHP_VERSION
        ]);
    }

    public static function metrics(PDO $pdo, int $transferId, array $m): void
    {
        $sql = "INSERT INTO transfer_metrics
          (transfer_id, source_outlet_id, destination_outlet_id, total_items, total_quantity, status, processing_time_ms, api_calls_made, cost_calculated, created_at, metadata)
          VALUES (:tid,:src,:dst,:items,:qty,:status,:ms,:api,:cost, NOW(), :meta)";
        $pdo->prepare($sql)->execute([
            'tid'=>$transferId,'src'=>$m['source_outlet_id']??null,'dst'=>$m['destination_outlet_id']??null,
            'items'=>$m['total_items']??0,'qty'=>$m['total_quantity']??0,'status'=>$m['status']??'pending',
            'ms'=>$m['processing_time_ms']??0,'api'=>$m['api_calls_made']??0,'cost'=>$m['cost_calculated']??0,
            'meta'=>json_encode($m['metadata']??[], JSON_UNESCAPED_SLASHES)
        ]);
    }
}
