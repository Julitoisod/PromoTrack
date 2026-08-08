<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNotificationsMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                 => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            // int, not bigint: must match Shield's `users.id` column type
            // (int(11) unsigned) for the foreign key below to be valid.
            'user_id'            => ['type' => 'int', 'constraint' => 11, 'unsigned' => true],
            'type'               => ['type' => 'varchar', 'constraint' => 60],
            'title'              => ['type' => 'varchar', 'constraint' => 150],
            'body'               => ['type' => 'text', 'null' => true],
            'related_entity_type' => ['type' => 'varchar', 'constraint' => 60, 'null' => true],
            'related_entity_id'  => ['type' => 'bigint', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'is_read'            => ['type' => 'tinyint', 'constraint' => 1, 'default' => 0],
            'created_at'         => ['type' => 'datetime', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['user_id', 'is_read']);
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('notifications', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('notifications', true);
    }
}
