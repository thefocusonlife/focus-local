<?php
final class EventLogger
{
    public function __construct(private \PDO $pdo) {}

    public function log(
        string $eventType,
        ?int $userId,
        ?string $anonId,
        array $reasonCodes = [],
        array $versions = [],
        array $payload = [],
        ?string $traceId = null,
        string $eventVersion = '1.0.0',
    ): void {
        $traceId = $traceId ?: RequestContext::traceId();
        $anonId = $anonId === null || $anonId === '' ? RequestContext::anonId() : $anonId;

        // enforce: at least one identity present (app-level, as you requested no DB constraints)
        if ($userId === null && ($anonId === null || $anonId === '')) {
            // If you prefer, remove this guard; but it saves you from useless rows.
            throw new \RuntimeException('Event must have user_id or anon_id.');
        }

        $sql = "
            INSERT INTO events_decision_events
              (trace_id, user_id, anon_id, event_type, event_version, reason_codes_json, versions_json, payload_json)
            VALUES
              (:trace_id, :user_id, :anon_id, :event_type, :event_version, :reason_codes, :versions, :payload)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':trace_id' => $traceId,
            ':user_id' => $userId,
            ':anon_id' => $anonId,
            ':event_type' => $eventType,
            ':event_version' => $eventVersion,
            ':reason_codes' => empty($reasonCodes)
                ? null
                : json_encode($reasonCodes, JSON_UNESCAPED_SLASHES),
            ':versions' => empty($versions) ? null : json_encode($versions, JSON_UNESCAPED_SLASHES),
            ':payload' => empty($payload) ? null : json_encode($payload, JSON_UNESCAPED_SLASHES),
        ]);
    }
}
