<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/DataPersistence.php';

class BoardController {
    private ?PDO  = null;

    public function __construct() {
        try {
            ->pdo = Database::getConnection();
        } catch (Throwable ) {
            ->pdo = null;
        }
    }

    public function index(): array {
        if (->pdo) {
            try {
                 = ->pdo->query('SELECT * FROM boards ORDER BY id ASC');
                 = ->fetchAll();
                if (!empty()) {
                    return ['success' => true, 'boards' => ];
                }
            } catch (Throwable ) {}
        }
         = DataPersistence::loadBoardJson();
        return ['success' => true, 'boards' => [['board'] ?? ['id' => 1, 'name' => 'Branch Planning 2026']]];
    }

    public function getFull(int ): array {
        if (->pdo) {
            try {
                 = ->pdo->prepare('SELECT * FROM boards WHERE id = :id');
                ->execute([':id' => ]);
                 = ->fetch();

                if () {
                     = ->pdo->prepare('SELECT * FROM board_columns WHERE board_id = :bid ORDER BY position ASC');
                    ->execute([':bid' => ]);
                     = ->fetchAll();

                     = [];
                     = [];
                    foreach ( as ) {
                        ['is_subitem'] = (bool)['is_subitem'];
                        ['settings'] = !empty(['settings']) ? json_decode(['settings'], true) : new stdClass();
                        if (['is_subitem']) {
                            [] = ;
                        } else {
                            [] = ;
                        }
                    }

                     = ->pdo->prepare('SELECT * FROM board_groups WHERE board_id = :bid ORDER BY position ASC');
                    ->execute([':bid' => ]);
                     = ->fetchAll();

                    if (!empty()) {
                         = ->pdo->prepare('
                            SELECT i.*, (SELECT COUNT(*) FROM item_updates u WHERE u.item_id = i.id) AS update_count
                            FROM items i
                            WHERE i.board_id = :bid
                            ORDER BY i.position ASC
                        ');
                        ->execute([':bid' => ]);
                         = ->fetchAll();

                         = [];
                         = [];

                        foreach ( as &) {
                            ['column_values'] = !empty(['column_values']) ? json_decode(['column_values'], true) : new stdClass();
                            ['update_count'] = (int)['update_count'];
                             = (int)['id'];
                             = (int)['group_id'];

                            if (['parent_id'] === null || ['parent_id'] === '0' || ['parent_id'] === '') {
                                ['subitems'] = [];
                                [][] = ;
                            } else {
                                 = (int)['parent_id'];
                                [][] = ;
                            }
                        }

                        foreach ( as  => ) {
                            foreach ( as  => &) {
                                if (isset([])) {
                                    []['subitems'] = ;
                                    break;
                                }
                            }
                        }

                         = [];
                        foreach ( as ) {
                             = (int)['id'];
                            ['items'] = isset([]) ? array_values([]) : [];
                            [] = ;
                        }

                        return [
                            'success' => true,
                            'board' => ,
                            'main_columns' => ,
                            'sub_columns' => ,
                            'groups' => ,
                            'server_time' => date('Y-m-d H:i:s')
                        ];
                    }
                }
            } catch (Throwable ) {}
        }

         = DataPersistence::loadBoardJson();
         = ['columns'] ?? [];
         = array_values(array_filter(, fn() => empty(['is_subitem']) && strtolower(['title'] ?? '') !== 'subtasks'));
         = array_values(array_filter(, fn() => !empty(['is_subitem'])));

        return [
            'success' => true,
            'board' => ['board'] ?? ['id' => 1, 'name' => 'Branch Planning 2026'],
            'main_columns' => ,
            'sub_columns' => ,
            'groups' => ['groups'] ?? [],
            'server_time' => date('Y-m-d H:i:s')
        ];
    }

    public function createColumn(int , array ): array {
         = trim(['title'] ?? 'New Column');
         = ['type'] ?? 'text';
         = !empty(['is_subitem']) ? 1 : 0;
         = 'col_' . time() . '_' . rand(100, 999);

         = [
            'id' => ,
            'board_id' => ,
            'title' => ,
            'type' => ,
            'is_subitem' => ,
            'position' => 99.0,
            'settings' => new stdClass()
        ];

        DataPersistence::createColumnInJson();

        if (->pdo) {
            try {
                 = ->pdo->prepare('INSERT INTO board_columns (id, board_id, title, type, is_subitem, position, settings) VALUES (:id, :bid, :title, :type, :is_sub, :pos, '{}')');
                ->execute([
                    ':id' => ,
                    ':bid' => ,
                    ':title' => ,
                    ':type' => ,
                    ':is_sub' => ,
                    ':pos' => 99.0
                ]);
            } catch (Throwable ) {}
        }

        return [
            'success' => true,
            'column' => 
        ];
    }

    public function deleteColumn(int , string ): array {
        DataPersistence::deleteColumnInJson();

        if (->pdo) {
            try {
                 = ->pdo->prepare('DELETE FROM board_columns WHERE board_id = :bid AND id = :id');
                ->execute([':bid' => , ':id' => ]);
            } catch (Throwable ) {}
        }

        return ['success' => true, 'deleted_id' => ];
    }

    public function saveBoardState(int , array ): array {
        DataPersistence::saveBoardJson();
        return ['success' => true, 'saved_at' => date('Y-m-d H:i:s')];
    }

    public function updateGroupTimeline(string|int , array ): array {
         = ['field'] ?? '';
         = ['value'] ?? null;
        DataPersistence::updateGroupTimelineInJson(, , );

        if (->pdo) {
            try {
                if (in_array(, ['soft_opening', 'grand_opening', 'timeline_start', 'timeline_end'])) {
                     = ->pdo->prepare('UPDATE board_groups SET ' .  . ' = :val WHERE id = :id');
                    ->execute([':val' => , ':id' => (int)]);
                }
            } catch (Throwable ) {}
        }

        return ['success' => true, 'group_id' => , 'field' => , 'value' => ];
    }
}
