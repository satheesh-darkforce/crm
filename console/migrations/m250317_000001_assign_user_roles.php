<?php

use yii\db\Migration;

/**
 * Bulk-assigns RBAC roles to all existing users based on their usertype column.
 *
 * usertype 1 → admin
 * usertype 2 → developer
 * usertype 3 → client
 * usertype 4 → sub_admin
 *
 * Note: user PK is `user_id` (bigint); auth_assignment.user_id is varchar(64).
 */
class m250317_000001_assign_user_roles extends Migration
{
    public function safeUp()
    {
        $now = time();

        // Map usertype → role name (must match auth_item.name exactly)
        $map = [
            1 => 'admin',
            2 => 'developer',
            3 => 'client',
            4 => 'sub_admin',
        ];

        foreach ($map as $usertype => $role) {
            $this->execute("
                INSERT INTO auth_assignment (item_name, user_id, created_at)
                SELECT :role, CAST(u.user_id AS CHAR) COLLATE utf8mb3_unicode_ci, :ts
                FROM user u
                LEFT JOIN auth_assignment aa
                       ON aa.user_id = CAST(u.user_id AS CHAR) COLLATE utf8mb3_unicode_ci
                      AND aa.item_name = :role2
                WHERE u.usertype = :usertype
                  AND u.status != 0
                  AND aa.user_id IS NULL
            ", [
                ':role'     => $role,
                ':role2'    => $role,
                ':ts'       => $now,
                ':usertype' => $usertype,
            ]);
        }
    }

    public function safeDown()
    {
        $this->execute("
            DELETE aa FROM auth_assignment aa
            INNER JOIN user u ON CAST(u.user_id AS CHAR) = aa.user_id
            WHERE aa.item_name IN ('admin', 'developer', 'client', 'sub_admin')
        ");
    }
}
