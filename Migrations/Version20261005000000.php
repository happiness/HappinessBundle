<?php

declare(strict_types=1);

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\HappinessBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20261005000000 extends AbstractMigration
{
    private const TABLE = 'kimai2_happiness_retainer_adjustments';

    public function getDescription(): string
    {
        return 'Create ' . self::TABLE . ' table for manual retainer hour balance adjustments';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable(self::TABLE)) {
            $table = $schema->createTable(self::TABLE);
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('project_id', 'integer', ['notnull' => true]);
            $table->addColumn('month', 'string', ['length' => 7, 'notnull' => true]);
            $table->addColumn('hours', 'float', ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['project_id', 'month'], 'UNIQ_HAPPINESS_RETAINER_ADJ');
            $table->addForeignKeyConstraint('kimai2_projects', ['project_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_HAPPINESS_RETAINER_ADJ_PROJECT');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable(self::TABLE)) {
            $schema->dropTable(self::TABLE);
        }
    }
}
