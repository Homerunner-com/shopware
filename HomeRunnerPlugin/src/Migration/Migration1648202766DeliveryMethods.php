<?php declare(strict_types=1);

namespace HomeRunnerPlugin\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1648202766DeliveryMethods extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1648202766;
    }

    public function update(Connection $connection): void
    {
        $query = <<<SQL
            CREATE TABLE IF NOT EXISTS `homerunner_methods` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                `cps` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                `from` VARCHAR(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                `to` VARCHAR(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                `created_at` datetime(3),
                `updated_at` datetime(3)
            )
            ENGINE = InnoDB
            DEFAULT CHARSET = utf8mb4
            COLLATE  = utf8mb4_unicode_ci;
        SQL;

        $connection->executeStatement($query);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
