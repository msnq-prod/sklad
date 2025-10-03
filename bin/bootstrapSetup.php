<?php
declare(strict_types=1);

$bootstrapPath = __DIR__ . '/../config/bootstrap.json';
if (!is_file($bootstrapPath)) {
    fwrite(STDOUT, "[bootstrap] config/bootstrap.json not found, skipping initialisation.\n");
    exit(0);
}

$jsonRaw = file_get_contents($bootstrapPath);
if ($jsonRaw === false) {
    fwrite(STDERR, "[bootstrap] Failed to read bootstrap JSON file.\n");
    exit(1);
}

try {
    $bootstrapData = json_decode($jsonRaw, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    fwrite(STDERR, "[bootstrap] Invalid JSON in config/bootstrap.json: " . $exception->getMessage() . "\n");
    exit(1);
}

if (!is_array($bootstrapData)) {
    fwrite(STDERR, "[bootstrap] Bootstrap JSON must decode into an object.\n");
    exit(1);
}

$requiredEnv = ['DB_HOSTNAME', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];
foreach ($requiredEnv as $envVar) {
    if (getenv($envVar) === false) {
        fwrite(STDERR, "[bootstrap] Missing required environment variable {$envVar}.\n");
        exit(1);
    }
}

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    getenv('DB_HOSTNAME'),
    getenv('DB_PORT') ?: '3306',
    getenv('DB_DATABASE')
);

try {
    $pdo = new PDO($dsn, getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $exception) {
    fwrite(STDERR, "[bootstrap] Database connection failed: " . $exception->getMessage() . "\n");
    exit(1);
}

$pdo->exec("SET NAMES 'utf8mb4'");

if (!empty($bootstrapData['config']) && is_array($bootstrapData['config'])) {
    $configStatement = $pdo->prepare(
        'INSERT INTO config (config_key, config_value) VALUES (:key, :value)
         ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)'
    );

    foreach ($bootstrapData['config'] as $key => $value) {
        if (!is_string($key) || $key === '') {
            continue;
        }

        if ($value === null) {
            $configStatement->bindValue(':value', null, PDO::PARAM_NULL);
        } elseif (is_scalar($value)) {
            $configStatement->bindValue(':value', (string)$value, PDO::PARAM_STR);
        } else {
            $configStatement->bindValue(':value', json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PDO::PARAM_STR);
        }

        $configStatement->bindValue(':key', $key, PDO::PARAM_STR);
        $configStatement->execute();
    }
}

if (!empty($bootstrapData['instance']) && is_array($bootstrapData['instance'])) {
    $instanceCount = (int)$pdo->query('SELECT COUNT(*) FROM instances')->fetchColumn();
    if ($instanceCount === 0) {
        $instanceInput = $bootstrapData['instance'];

        $adminUserId = null;
        if (!empty($instanceInput['admin_user_email']) && is_string($instanceInput['admin_user_email'])) {
            $userLookup = $pdo->prepare('SELECT users_userid FROM users WHERE users_email = :email LIMIT 1');
            $userLookup->execute([':email' => $instanceInput['admin_user_email']]);
            $adminUserId = $userLookup->fetchColumn();
        }

        if (!$adminUserId && !empty($instanceInput['admin_user_username']) && is_string($instanceInput['admin_user_username'])) {
            $userLookup = $pdo->prepare('SELECT users_userid FROM users WHERE users_username = :username LIMIT 1');
            $userLookup->execute([':username' => $instanceInput['admin_user_username']]);
            $adminUserId = $userLookup->fetchColumn();
        }

        if (!$adminUserId) {
            $adminUserId = $pdo->query('SELECT users_userid FROM users ORDER BY users_userid ASC LIMIT 1')->fetchColumn();
        }

        if (!$adminUserId) {
            fwrite(STDERR, "[bootstrap] Unable to locate an admin user to assign to the default instance.\n");
            exit(1);
        }

        $instanceActions = [];
        require __DIR__ . '/../src/common/libs/Auth/instanceActions.php';
        if (!is_array($instanceActions)) {
            $instanceActions = [];
        }

        $defaultStatuses = [
            ['name' => 'Added to RMS', 'description' => 'Default', 'foreground' => '#000000', 'background' => '#F5F5F5', 'rank' => 0, 'released' => 0],
            ['name' => 'Targeted', 'description' => 'Being targeted as a lead', 'foreground' => '#000000', 'background' => '#F5F5F5', 'rank' => 1, 'released' => 0],
            ['name' => 'Quote Sent', 'description' => 'Waiting for client confirmation', 'foreground' => '#000000', 'background' => '#ffdd99', 'rank' => 2, 'released' => 0],
            ['name' => 'Confirmed', 'description' => 'Booked in with client', 'foreground' => '#ffffff', 'background' => '#66ff66', 'rank' => 3, 'released' => 0],
            ['name' => 'Prep', 'description' => 'Being prepared for dispatch', 'foreground' => '#000000', 'background' => '#ffdd99', 'rank' => 4, 'released' => 0],
            ['name' => 'Dispatched', 'description' => 'Sent to client', 'foreground' => '#ffffff', 'background' => '#66ff66', 'rank' => 5, 'released' => 0],
            ['name' => 'Returned', 'description' => 'Waiting to be checked in ', 'foreground' => '#000000', 'background' => '#ffdd99', 'rank' => 6, 'released' => 0],
            ['name' => 'Closed', 'description' => 'Pending move to Archive', 'foreground' => '#000000', 'background' => '#F5F5F5', 'rank' => 7, 'released' => 0],
            ['name' => 'Cancelled', 'description' => 'Project Cancelled', 'foreground' => '#000000', 'background' => '#F5F5F5', 'rank' => 8, 'released' => 1],
            ['name' => 'Lead Lost', 'description' => 'Project Cancelled', 'foreground' => '#000000', 'background' => '#F5F5F5', 'rank' => 9, 'released' => 1],
        ];

        $defaultAssignmentStatuses = [
            'Pending pick',
            'Picked',
            'Prepping',
            'Tested',
            'Packed',
            'Dispatched',
            'Awaiting Check-in',
            'Case opened',
            'Unpacked',
            'Tested',
            'Stored',
        ];

        $pdo->beginTransaction();

        try {
            $insertInstance = $pdo->prepare(
                'INSERT INTO instances (
                    instances_name,
                    instances_address,
                    instances_phone,
                    instances_email,
                    instances_website,
                    instances_config_currency,
                    instances_billingUser,
                    instances_storageLimit,
                    instances_storageEnabled,
                    instances_assetLimit,
                    instances_userLimit,
                    instances_planName,
                    instances_suspended,
                    instances_suspendedReasonType,
                    instances_suspendedReason,
                    instances_calendarConfig,
                    instances_projectLimit,
                    instances_planStripeCustomerId,
                    instances_serverNotes
                ) VALUES (
                    :name,
                    :address,
                    :phone,
                    :email,
                    :website,
                    :currency,
                    :billingUser,
                    :storageLimit,
                    :storageEnabled,
                    :assetLimit,
                    :userLimit,
                    :planName,
                    :suspended,
                    :suspendedReasonType,
                    :suspendedReason,
                    :calendarConfig,
                    :projectLimit,
                    :planStripeCustomerId,
                    :serverNotes
                )'
            );

            $insertInstance->execute([
                ':name' => $instanceInput['name'] ?? 'Default Company',
                ':address' => $instanceInput['address'] ?? ($instanceInput['name'] ?? 'Default Company'),
                ':phone' => $instanceInput['phone'] ?? null,
                ':email' => $instanceInput['email'] ?? null,
                ':website' => $instanceInput['website'] ?? null,
                ':currency' => $instanceInput['currency'] ?? 'GBP',
                ':billingUser' => $instanceInput['billing_user_userid'] ?? $adminUserId,
                ':storageLimit' => isset($instanceInput['storage_limit']) ? (int)$instanceInput['storage_limit'] : 0,
                ':storageEnabled' => isset($instanceInput['storage_enabled']) ? (int)$instanceInput['storage_enabled'] : 1,
                ':assetLimit' => isset($instanceInput['asset_limit']) ? (int)$instanceInput['asset_limit'] : 0,
                ':userLimit' => isset($instanceInput['user_limit']) ? (int)$instanceInput['user_limit'] : 0,
                ':planName' => $instanceInput['plan_name'] ?? '',
                ':suspended' => isset($instanceInput['suspended']) ? (int)$instanceInput['suspended'] : 0,
                ':suspendedReasonType' => $instanceInput['suspended_reason_type'] ?? null,
                ':suspendedReason' => $instanceInput['suspended_reason'] ?? null,
                ':calendarConfig' => $instanceInput['calendar_config'] ?? '{"showProjectStatus":true,"showSubProjects":true,"useCustomWeekNumbers":true,"defaultView":"dayGridMonth"}',
                ':projectLimit' => isset($instanceInput['project_limit']) ? (int)$instanceInput['project_limit'] : 0,
                ':planStripeCustomerId' => $instanceInput['plan_stripe_customer_id'] ?? null,
                ':serverNotes' => $instanceInput['server_notes'] ?? null,
            ]);

            $instanceId = (int)$pdo->lastInsertId();

            $positionStatement = $pdo->prepare(
                'INSERT INTO instancePositions (
                    instances_id,
                    instancePositions_displayName,
                    instancePositions_rank,
                    instancePositions_actions
                ) VALUES (
                    :instance,
                    :name,
                    :rank,
                    :actions
                )'
            );

            $positionStatement->execute([
                ':instance' => $instanceId,
                ':name' => $instanceInput['position_name'] ?? 'Administrator',
                ':rank' => 1,
                ':actions' => implode(',', array_keys($instanceActions)),
            ]);

            $positionId = (int)$pdo->lastInsertId();

            $userInstanceStatement = $pdo->prepare(
                'INSERT INTO userInstances (
                    users_userid,
                    instancePositions_id,
                    userInstances_label
                ) VALUES (
                    :user,
                    :position,
                    :label
                )'
            );

            $userInstanceStatement->execute([
                ':user' => $adminUserId,
                ':position' => $positionId,
                ':label' => $instanceInput['admin_role_label'] ?? 'Instance Owner',
            ]);

            $updateUser = $pdo->prepare('UPDATE users SET users_selectedInstanceIDLast = :instance WHERE users_userid = :user');
            $updateUser->execute([
                ':instance' => $instanceId,
                ':user' => $adminUserId,
            ]);

            $projectTypeStatement = $pdo->prepare('INSERT INTO projectsTypes (instances_id, projectsTypes_name) VALUES (:instance, :name)');
            $projectTypeStatement->execute([
                ':instance' => $instanceId,
                ':name' => $instanceInput['project_type_name'] ?? 'Full Project',
            ]);

            $statusStatement = $pdo->prepare(
                'INSERT INTO projectsStatuses (
                    instances_id,
                    projectsStatuses_name,
                    projectsStatuses_description,
                    projectsStatuses_foregroundColour,
                    projectsStatuses_backgroundColour,
                    projectsStatuses_rank,
                    projectsStatuses_assetsReleased
                ) VALUES (
                    :instance,
                    :name,
                    :description,
                    :foreground,
                    :background,
                    :rank,
                    :released
                )'
            );

            foreach ($defaultStatuses as $status) {
                $statusStatement->execute([
                    ':instance' => $instanceId,
                    ':name' => $status['name'],
                    ':description' => $status['description'],
                    ':foreground' => $status['foreground'],
                    ':background' => $status['background'],
                    ':rank' => $status['rank'],
                    ':released' => $status['released'],
                ]);
            }

            $assignmentStatement = $pdo->prepare(
                'INSERT INTO assetsAssignmentsStatus (
                    instances_id,
                    assetsAssignmentsStatus_name,
                    assetsAssignmentsStatus_order
                ) VALUES (
                    :instance,
                    :name,
                    :order
                )'
            );

            foreach ($defaultAssignmentStatuses as $order => $assignmentName) {
                $assignmentStatement->execute([
                    ':instance' => $instanceId,
                    ':name' => $assignmentName,
                    ':order' => $order,
                ]);
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            fwrite(STDERR, "[bootstrap] Failed to create default instance: " . $exception->getMessage() . "\n");
            exit(1);
        }
    }
}
