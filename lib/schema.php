<?php
declare(strict_types=1);
function schema_ready(PDO $pdo): bool
{
    $expected = ['schema_migrations', 'users', 'clients', 'money_cases', 'safety_cases',
        'money_rebalancing_plans', 'client_care_plans', 'client_care_meetings',
        'client_care_follow_ups', 'v_care_dashboard'];
    $objects = $pdo->query('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchAll(PDO::FETCH_COLUMN);
    if (array_diff($expected, $objects)) {
        return false;
    }
    $versions = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    if (array_diff(['001_schema', '002_modules', '003_dashboard'], $versions)) {
        return false;
    }
    $keys = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='safety_cases' AND CONSTRAINT_TYPE='FOREIGN KEY'")->fetchAll(PDO::FETCH_COLUMN);
    if (array_diff(['fk_safety_client', 'fk_safety_advisor'], $keys)) {
        return false;
    }
    $pdo->query('SELECT overdue, this_week, this_month FROM v_care_dashboard LIMIT 0');
    return true;
}
