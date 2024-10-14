<?php

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../../');
$dotenv->load();

define('CONCIERGE_ROOT', $_ENV['CONCIERGE_ROOT']);
define('FRONT_ROOT', $_ENV['FRONT_ROOT']);

function getDashboardAnalytics($duration = 'weekly') {
    $databaseService = new DatabaseService();
    $conn = $databaseService->getConnection();
    $result = [
        'stats' => [
            'daily' => 0,
            'weekly' => 0,
            'monthly' => 0
        ],
        'line' => [],
        'hot_story_fragments' => []
    ];

    // Helper function to get date filter
    function getDateFilter($duration) {
        switch ($duration) {
            case 'daily':
                return "AND a.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
            case 'weekly':
                return "AND a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case 'monthly':
            default:
                return "AND a.created_at >= DATE_SUB(NOW(), INTERVAL 28 DAY)";
        }
    }

    // Stats query for all durations
    $stats_query = "
    SELECT
        SUM(CASE WHEN a.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) as daily,
        SUM(CASE WHEN a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as weekly,
        SUM(CASE WHEN a.created_at >= DATE_SUB(NOW(), INTERVAL 28 DAY) THEN 1 ELSE 0 END) as monthly
    FROM actions a
    WHERE a.verb = 'PAGEVIEWED'";
    $stats_stmt = $conn->prepare($stats_query);
    if ($stats_stmt->execute()) {
        $result['stats'] = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        // Convert to integers
        $result['stats'] = array_map('intval', $result['stats']);
    }

    // Line data query
    $interval_expression = $duration === 'daily' ? "HOUR" : "DAY";
    $limit = $duration === 'daily' ? 24 : ($duration === 'weekly' ? 7 : 28);
    $line_query = "
    SELECT
        a.verb,
        FLOOR(TIMESTAMPDIFF($interval_expression, a.created_at, NOW())) AS time_interval,
        COUNT(*) AS total_count
    FROM actions a
    WHERE 1=1 " . getDateFilter($duration) . "
    GROUP BY a.verb, time_interval
    ORDER BY a.verb, time_interval";
    $line_stmt = $conn->prepare($line_query);
    if ($line_stmt->execute()) {
        $line_data = $line_stmt->fetchAll(PDO::FETCH_ASSOC);
        $result['line'] = processLineData($line_data, $limit);
    }

    // Hot story fragments query
    $hot_query = "
    SELECT
        c.object_id AS id,
        COUNT(*) AS total_events
    FROM actions a
    JOIN corpus c ON a.object_id = c.id
    WHERE c.object_type = 'StoryFragment' " . getDateFilter($duration) . "
    GROUP BY c.object_id
    ORDER BY total_events DESC
    LIMIT 5";
    $hot_stmt = $conn->prepare($hot_query);
    if ($hot_stmt->execute()) {
        $result['hot_story_fragments'] = $hot_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        "data" => $result,
        "message" => "Success.",
        "error" => null
    ]);
    return 200;
}

function processLineData($data, $limit) {
    $result = [];
    foreach ($data as $row) {
        $verb = $row['verb'];
        if (!isset($result[$verb])) {
            $result[$verb] = [
                'id' => $verb,
                'data' => array_fill(0, $limit, ['x' => 0, 'y' => 0])
            ];
        }
        $result[$verb]['data'][$row['time_interval']] = [
            'x' => intval($row['time_interval']),
            'y' => intval($row['total_count'])
        ];
    }
    return array_values($result);
}

function getAnalytics($id, $type, $duration) {
    $databaseService = new DatabaseService();
    $conn = $databaseService->getConnection();

    // Determine the date range and interval based on the duration
    $dateFilter = "";
    $intervalExpression = "";
    $limit = 0;
    switch ($duration) {
	case 'daily':
            $dateFilter = "AND a.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
            $intervalExpression = "FLOOR(TIMESTAMPDIFF(HOUR, a.created_at, NOW()))";
            $limit = 24;
            break;
        case 'weekly':
            $dateFilter = "AND a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            $intervalExpression = "FLOOR(TIMESTAMPDIFF(DAY, a.created_at, NOW()))";
            $limit = 7;
            break;
        case 'monthly':
            $dateFilter = "AND a.created_at >= DATE_SUB(NOW(), INTERVAL 28 DAY)";
            $intervalExpression = "FLOOR(TIMESTAMPDIFF(DAY, a.created_at, NOW()))";
            $limit = 28;
            break;
    }

    if ($type === "storyfragment") {
        $pie_query = "
        WITH storyfragment AS (
            SELECT id, object_id, object_name
            FROM corpus
            WHERE object_type = 'StoryFragment' AND object_id = :id
        ),
        storyfragment_actions AS (
            SELECT
                sf.id,
                sf.object_id,
                sf.object_name,
                'StoryFragment' AS object_type,
                a.verb,
                COUNT(a.id) AS verb_count
            FROM storyfragment sf
            LEFT JOIN actions a ON sf.id = a.object_id
            WHERE 1=1 $dateFilter
            GROUP BY sf.id, sf.object_id, sf.object_name, a.verb
        ),
        pane_actions AS (
            SELECT
                c.id,
                c.object_id,
                c.object_name,
                'Pane' AS object_type,
                a.verb,
                COUNT(a.id) AS verb_count
            FROM actions a
            JOIN corpus c ON a.object_id = c.id
            WHERE a.parent_id = (SELECT id FROM storyfragment)
            AND c.object_type = 'Pane'
            $dateFilter
            GROUP BY c.id, c.object_id, c.object_name, a.verb
        )
        SELECT * FROM storyfragment_actions
        UNION ALL
        SELECT * FROM pane_actions
        ORDER BY object_type DESC, id, verb_count DESC
        ";

        $line_query = "
        WITH storyfragment AS (
            SELECT id, object_id, object_name
            FROM corpus
            WHERE object_type = 'StoryFragment' AND object_id = :id
        ),
        storyfragment_actions AS (
            SELECT
                sf.id,
                sf.object_id,
                sf.object_name,
                'StoryFragment' AS object_type,
                a.verb,
                $intervalExpression AS time_interval,
                COUNT(a.id) AS total_count
            FROM storyfragment sf
            LEFT JOIN actions a ON sf.id = a.object_id
            WHERE 1=1 $dateFilter
            GROUP BY sf.id, sf.object_id, sf.object_name, a.verb, time_interval
        ),
        pane_actions AS (
            SELECT
                c.id,
                c.object_id,
                c.object_name,
                'Pane' AS object_type,
                a.verb,
                $intervalExpression AS time_interval,
                COUNT(a.id) AS total_count
            FROM actions a
            JOIN corpus c ON a.object_id = c.id
            WHERE a.parent_id = (SELECT id FROM storyfragment)
            AND c.object_type = 'Pane'
            $dateFilter
            GROUP BY c.id, c.object_id, c.object_name, a.verb, time_interval
        )
        SELECT * FROM storyfragment_actions
        UNION ALL
        SELECT * FROM pane_actions
        ORDER BY object_type DESC, id, verb, time_interval
        ";
    } elseif ($type === "pane") {
        $pie_query = "
        SELECT
            c.id,
            c.object_id,
            c.object_name,
            'Pane' AS object_type,
            a.verb,
            COUNT(a.id) AS verb_count
        FROM corpus c
        LEFT JOIN actions a ON c.id = a.object_id
        WHERE c.object_type = 'Pane' AND c.object_id = :id
        $dateFilter
        GROUP BY c.id, c.object_id, c.object_name, a.verb
        ORDER BY verb_count DESC
        ";

        $line_query = "
        SELECT
            c.id,
            c.object_id,
            c.object_name,
            'Pane' AS object_type,
            a.verb,
            $intervalExpression AS time_interval,
            COUNT(a.id) AS total_count
        FROM corpus c
        LEFT JOIN actions a ON c.id = a.object_id
        WHERE c.object_type = 'Pane' AND c.object_id = :id
        $dateFilter
        GROUP BY c.id, c.object_id, c.object_name, a.verb, time_interval
        ORDER BY a.verb, time_interval
        ";
    } else {
        echo json_encode(array(
            "data" => null,
            "message" => "Invalid type specified.",
            "error" => "Invalid type"
        ));
        return 400;
    }

    $pie_stmt = $conn->prepare($pie_query);
    $pie_stmt->bindParam(':id', $id);

    $line_stmt = $conn->prepare($line_query);
    $line_stmt->bindParam(':id', $id);

    if ($pie_stmt->execute() && $line_stmt->execute()) {
        $pie_rows = $pie_stmt->fetchAll(PDO::FETCH_ASSOC);
        $line_rows = $line_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process pie data
        $pie_result = array();
        foreach ($pie_rows as $row) {
            $object_id = $row['object_id'] ?? $id;
            if (!isset($pie_result[$object_id])) {
                $pie_result[$object_id] = array(
                    'id' => $row['id'] ?? $object_id,
                    'object_id' => $object_id,
                    'object_name' => $row['object_name'] ?? ($type === 'storyfragment' ? 'Story Fragment' : 'Pane'),
                    'object_type' => $row['object_type'] ?? $type,
                    'total_actions' => 0,
                    'verbs' => array()
                );
            }
            if (isset($row['verb']) && isset($row['verb_count'])) {
                $pie_result[$object_id]['verbs'][] = array(
                    'id' => $row['verb'],
                    'value' => intval($row['verb_count'])
                );
                $pie_result[$object_id]['total_actions'] += intval($row['verb_count']);
            }
        }

        // Process line data
        $line_result = array();
        foreach ($line_rows as $row) {
            $object_id = $row['object_id'] ?? $id;
            if (!isset($line_result[$object_id])) {
                $line_result[$object_id] = array(
                    'id' => $row['id'] ?? $object_id,
                    'object_id' => $object_id,
                    'object_name' => $row['object_name'] ?? ($type === 'storyfragment' ? 'Story Fragment' : 'Pane'),
                    'object_type' => $row['object_type'] ?? $type,
                    'total_actions' => 0,
                    'verbs' => array()
                );
            }
            if (isset($row['verb']) && isset($row['time_interval']) && isset($row['total_count'])) {
                $verb = $row['verb'];
                if (!isset($line_result[$object_id]['verbs'][$verb])) {
                    $line_result[$object_id]['verbs'][$verb] = array(
                        'id' => $verb,
                        'data' => array()
                    );
                }
                $line_result[$object_id]['verbs'][$verb]['data'][] = array(
                    'x' => intval($row['time_interval']),
                    'y' => intval($row['total_count'])
                );
                $line_result[$object_id]['total_actions'] += intval($row['total_count']);
            }
        }

        // Fill in missing intervals with zero counts for line data
        foreach ($line_result as &$item) {
            foreach ($item['verbs'] as &$series) {
                $existing_intervals = array_column($series['data'], 'x');
                for ($i = 1; $i <= $limit; $i++) {
                    if (!in_array($i, $existing_intervals)) {
                        $series['data'][] = array('x' => $i, 'y' => 0);
                    }
                }
                usort($series['data'], function($a, $b) {
                    return $a['x'] - $b['x'];
                });
            }
            $item['verbs'] = array_values($item['verbs']);
        }

        $final_result = array(
            'pie' => array_values($pie_result),
            'line' => array_values($line_result)
        );

        echo json_encode(array(
            "data" => $final_result,
            "message" => "Success.",
            "error" => null
        ));
        return 200;
    } else {
        echo json_encode(array(
            "data" => null,
            "message" => "Failed to execute query.",
            "error" => $pie_stmt->errorInfo() ?: $line_stmt->errorInfo()
        ));
        return 500;
    }
}

//function getPaneDetailsPie($storyFragmentId)
//{
//  $databaseService = new DatabaseService();
//  $conn = $databaseService->getConnection();
//  $actions_table_name = 'actions';
//  $corpus_table_name = 'corpus';
//
//  // initial SQL lookup
//  $activity_query = "SELECT p.object_id as paneId, sum((case when a.verb = 'CLICKED' then 1 else 0 end)) as clicked,"
//    . " sum((case when a.verb = 'READ' then 1 else 0 end)) as red,"
//    . " sum((case when a.verb = 'GLOSSED' then 1 else 0 end)) as glossed"
//    . " from " . $actions_table_name . " as a"
//    . " left join " . $corpus_table_name . " as p on a.object_id=p.id"
//    . " left join " . $corpus_table_name . " as sf on a.parent_id=sf.id"
//    . " where sf.object_id=:storyFragmentId"
//    . " group by paneId;";
//  $activity_stmt = $conn->prepare($activity_query);
//  $activity_stmt->bindParam(':storyFragmentId', $storyFragmentId);
//  if ($activity_stmt->execute()) {
//    $rows = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
//    echo json_encode(array(
//      "data" => json_encode($rows),
//      "message" => "Success.",
//      "error" => null
//    ));
//    return (200);
//  } else {
//    return (500);
//  }
//  echo json_encode(array(
//    "data" => null,
//    "message" => "Success.",
//    "error" => null
//  ));
//  return (200);
//}
//
//function getPaneActivitySwarm()
//{
//  $databaseService = new DatabaseService();
//  $conn = $databaseService->getConnection();
//  $actions_table_name = 'actions';
//  $corpus_table_name = 'corpus';
//
//  // initial SQL lookup
//  $activity_query = "select p.object_id as paneId, p.object_name as title," .
//    " timestampdiff(hour, max(a.created_at),now()) as hours_since_activity," .
//    " sum((case when a.verb='CLICKED' and a.object_id=p.id then 1 else 0 end)) as clicked," .
//    " sum((case when a.verb='READ' and a.object_id=p.id then 1 else 0 end)) as red," .
//    " sum((case when a.verb='GLOSSED' and a.object_id=p.id then 1 else 0 end)) as glossed" .
//    " from " . $actions_table_name . " as a" .
//    " left join " . $corpus_table_name . " as p on a.object_id=p.id" .
//    " where p.object_type='Pane'" .
//    " group by paneId;";
//  $activity_stmt = $conn->prepare($activity_query);
//  if ($activity_stmt->execute()) {
//    $rows = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
//    echo json_encode(array(
//      "data" => json_encode($rows),
//      "message" => "Success.",
//      "error" => null
//    ));
//    return (200);
//  } else {
//    return (500);
//  }
//  echo json_encode(array(
//    "data" => null,
//    "message" => "Success.",
//    "error" => null
//  ));
//  return (200);
//}
//
//
//function getStoryFragmentActivitySwarm()
//{
//  $databaseService = new DatabaseService();
//  $conn = $databaseService->getConnection();
//  $actions_table_name = 'actions';
//  $corpus_table_name = 'corpus';
//
//  // initial SQL lookup
//  $activity_query = "select sf.object_id as storyFragmentId, sf.object_name as title," .
//    " timestampdiff(hour, max(a.created_at),now()) as hours_since_activity," .
//    " sum((case when a.verb='CLICKED' and a.object_id=p.id then 1 else 0 end)) as clicked," .
//    " sum((case when a.verb='READ' and a.object_id=p.id then 1 else 0 end)) as red," .
//    " sum((case when a.verb='GLOSSED' and a.object_id=p.id then 1 else 0 end)) as glossed," .
//    " sum((case when a.verb='ENTERED' and a.object_id=sf.id then 1 else 0 end)) as entered," .
//    " sum((case when a.verb='CONNECTED' and a.parent_id=sf.id then 1 else 0 end)) as discovered" .
//    " from " . $actions_table_name . " as a" .
//    " left join " . $corpus_table_name . " as sf on a.parent_id=sf.id left join " . $corpus_table_name . " as p on a.object_id=p.id" .
//    " where sf.object_type='StoryFragment' group by storyFragmentId;";
//  $activity_stmt = $conn->prepare($activity_query);
//  if ($activity_stmt->execute()) {
//    $rows = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
//    echo json_encode(array(
//      "data" => json_encode($rows),
//      "message" => "Success.",
//      "error" => null
//    ));
//    return (200);
//  } else {
//    return (500);
//  }
//  echo json_encode(array(
//    "data" => null,
//    "message" => "Success.",
//    "error" => null
//  ));
//  return (200);
//}
//
//function getStoryFragmentDaysSince()
//{
//  $databaseService = new DatabaseService();
//  $conn = $databaseService->getConnection();
//  $actions_table_name = 'actions';
//  $corpus_table_name = 'corpus';
//
//  // initial SQL lookup
//  $activity_query = "select sf.object_id as storyFragmentId, sf.object_name as title," .
//    " timestampdiff(hour, max(a.created_at),now()) as hours_since_activity," .
//    " sum((case when a.verb='CLICKED' and a.parent_id=sf.id then 1 else 0 end)) as clicked," .
//    " sum((case when a.verb='READ' and a.parent_id=sf.id then 1 else 0 end)) as red," .
//    " sum((case when a.verb='GLOSSED' and a.parent_id=sf.id then 1 else 0 end)) as glossed," .
//    " sum((case when a.verb='ENTERED' and a.object_id=sf.id then 1 else 0 end)) as entered," .
//    " sum((case when a.verb='CONNECTED' and a.parent_id=sf.id then 1 else 0 end)) as discovered" .
//    " from " . $actions_table_name . " as a" .
//    " left join " . $corpus_table_name . " as sf on a.parent_id=sf.id" .
//    " group by storyFragmentId;";
//  $activity_stmt = $conn->prepare($activity_query);
//  if ($activity_stmt->execute()) {
//    $rows = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
//    echo json_encode(array(
//      "data" => json_encode($rows),
//      "message" => "Success.",
//      "error" => null
//    ));
//    return (200);
//  } else {
//    return (500);
//  }
//  echo json_encode(array(
//    "data" => null,
//    "message" => "Success.",
//    "error" => null
//  ));
//  return (200);
//}
//
//
//function getPanesDaysSince()
//{
//  $databaseService = new DatabaseService();
//  $conn = $databaseService->getConnection();
//  $actions_table_name = 'actions';
//  $corpus_table_name = 'corpus';
//
//  // initial SQL lookup
//  $activity_query = "select p.object_id as paneId, p.object_name as title," .
//    " timestampdiff(hour, max(a.created_at),now()) as hours_since_activity," .
//    " sum((case when a.verb='CLICKED' and a.object_id=p.id then 1 else 0 end)) as clicked," .
//    " sum((case when a.verb='READ' and a.object_id=p.id then 1 else 0 end)) as red," .
//    " sum((case when a.verb='GLOSSED' and a.object_id=p.id then 1 else 0 end)) as glossed" .
//    " from " . $actions_table_name . " as a" .
//    " left join " . $corpus_table_name . " as p on a.object_id=p.id" .
//    " group by paneId;";
//  $activity_stmt = $conn->prepare($activity_query);
//  if ($activity_stmt->execute()) {
//    $rows = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
//    echo json_encode(array(
//      "data" => json_encode($rows),
//      "message" => "Success.",
//      "error" => null
//    ));
//    return (200);
//  } else {
//    return (500);
//  }
//  echo json_encode(array(
//    "data" => null,
//    "message" => "Success.",
//    "error" => null
//  ));
//  return (200);
//}
//
//function getRecentDailyActivity()
//{
//  $databaseService = new DatabaseService();
//  $conn = $databaseService->getConnection();
//  $actions_table_name = 'actions';
//
//  // initial SQL lookup
//  $activity_query = "select datediff(now(),created_at) as daysSince," .
//    " sum((case when verb='ENTERED' then 1 else 0 end)) as entered," .
//    " sum((case when verb='GLOSSED' then 1 else 0 end)) as glossed," .
//    " sum((case when verb='READ' then 1 else 0 end)) as red," .
//    " sum((case when verb='CLICKED' then 1 else 0 end)) as clicked," .
//    " sum((case when verb='CONNECTED' then 1 else 0 end)) as discovered" .
//    " from " . $actions_table_name .
//    " where DATE(created_at) >= CURDATE() - INTERVAL 28 DAY" .
//    " group by DATE(created_at);";
//  $activity_stmt = $conn->prepare($activity_query);
//
//  if ($activity_stmt->execute()) {
//    $rows = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
//    echo json_encode(array(
//      "data" => json_encode($rows),
//      "message" => "Success.",
//      "error" => null
//    ));
//    return (200);
//  } else {
//    return (500);
//  }
//  echo json_encode(array(
//    "data" => null,
//    "message" => "Success.",
//    "error" => null
//  ));
//  return (200);
//}
//
//function getDashboardPayloads()
//{
//
//  $databaseService = new DatabaseService();
//  $conn = $databaseService->getConnection();
//  $actions_table_name = 'actions';
//  $corpus_table_name = 'corpus';
//
//  $activity_query = "select datediff(now(),created_at) as daysSince," .
//    " sum((case when verb='ENTERED' then 1 else 0 end)) as entered," .
//    " sum((case when verb='GLOSSED' then 1 else 0 end)) as glossed," .
//    " sum((case when verb='READ' then 1 else 0 end)) as red," .
//    " sum((case when verb='CLICKED' then 1 else 0 end)) as clicked," .
//    " sum((case when verb='CONNECTED' then 1 else 0 end)) as discovered" .
//    " from " . $actions_table_name .
//    " where DATE(created_at) >= CURDATE() - INTERVAL 28 DAY" .
//    " group by DATE(created_at);";
//  $activity_stmt = $conn->prepare($activity_query);
//  if ($activity_stmt->execute()) {
//    $recentDailyActivity = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
//  } else {
//    return (500);
//  }
//
//  $activity_query = "select sf.object_id as storyFragmentId, sf.object_name as title," .
//    " timestampdiff(hour, max(a.created_at),now()) as hours_since_activity," .
//    " sum((case when a.verb='CLICKED' and a.object_id=p.id then 1 else 0 end)) as clicked," .
//    " sum((case when a.verb='READ' and a.object_id=p.id then 1 else 0 end)) as red," .
//    " sum((case when a.verb='GLOSSED' and a.object_id=p.id then 1 else 0 end)) as glossed," .
//    " sum((case when a.verb='ENTERED' and a.object_id=sf.id then 1 else 0 end)) as entered," .
//    " sum((case when a.verb='CONNECTED' and a.parent_id=sf.id then 1 else 0 end)) as discovered" .
//    " from " . $actions_table_name . " as a" .
//    " left join " . $corpus_table_name . " as sf on a.parent_id=sf.id left join " . $corpus_table_name . " as p on a.object_id=p.id" .
//    " where sf.object_type='StoryFragment' group by storyFragmentId;";
//  $activity_stmt = $conn->prepare($activity_query);
//  if ($activity_stmt->execute()) {
//    $storyFragmentActivitySwarm = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
//  } else {
//    return (500);
//  }
//
//  $activity_query = "select p.object_id as paneId, p.object_name as title," .
//    " timestampdiff(hour, max(a.created_at),now()) as hours_since_activity," .
//    " sum((case when a.verb='CLICKED' and a.object_id=p.id then 1 else 0 end)) as clicked," .
//    " sum((case when a.verb='READ' and a.object_id=p.id then 1 else 0 end)) as red," .
//    " sum((case when a.verb='GLOSSED' and a.object_id=p.id then 1 else 0 end)) as glossed" .
//    " from " . $actions_table_name . " as a" .
//    " left join " . $corpus_table_name . " as p on a.object_id=p.id" .
//    " where p.object_type='Pane'" .
//    " group by paneId;";
//  $activity_stmt = $conn->prepare($activity_query);
//  if ($activity_stmt->execute()) {
//    $paneActivitySwarm = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
//  } else {
//    return (500);
//  }
//
//  $activity_query = "select count(distinct(id)) as uniqueSessions, count(distinct(utmSource)) as uniqueUtmSource," .
//    " count(distinct(campaign_id)) as uniqueUtmCampaign, count(distinct(utmTerm)) as uniqueUtmTerm" .
//    " from visits WHERE DATE(updated_at) >= CURDATE() - INTERVAL 7 DAY;";
//  $activity_stmt = $conn->prepare($activity_query);
//  if ($activity_stmt->execute()) {
//    $recentMetrics = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
//  } else {
//    return (500);
//  }
//
//  echo json_encode(array(
//    "data" => json_encode(array(
//      "recentDailyActivity" => $recentDailyActivity,
//      "storyFragmentActivitySwarm" => $storyFragmentActivitySwarm,
//      "paneActivitySwarm" => $paneActivitySwarm,
//      "recentMetrics" => $recentMetrics
//    )),
//    "message" => "Success.",
//    "error" => null
//  ));
//  return (200);
//}
