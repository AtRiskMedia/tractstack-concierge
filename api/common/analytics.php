<?php

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../../');
$dotenv->load();

define('CONCIERGE_ROOT', $_ENV['CONCIERGE_ROOT']);
define('FRONT_ROOT', $_ENV['FRONT_ROOT']);
define('STORYKEEP_ROOT', $_ENV['STORYKEEP_ROOT']);

function getAnalytics($id, $type, $duration) {
    $databaseService = new DatabaseService();
    $conn = $databaseService->getConnection();

    // Determine the date range based on the duration
    $dateFilter = "";
    switch ($duration) {
        case 'daily':
            $dateFilter = "AND a.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'weekly':
            $dateFilter = "AND a.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
            break;
        case 'monthly':
            $dateFilter = "AND a.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
            break;
        default:
            // No filter if duration is not specified or invalid
            break;
    }

    if ($type === "storyfragment") {
        $activity_query = "
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
    } elseif ($type === "pane") {
        $activity_query = "
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
    } else {
        echo json_encode(array(
            "data" => null,
            "message" => "Invalid type specified.",
            "error" => "Invalid type"
        ));
        return 400;
    }

    $activity_stmt = $conn->prepare($activity_query);
    $activity_stmt->bindParam(':id', $id);
    
    if ($activity_stmt->execute()) {
        $rows = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process the rows to create an array of objects with verb counts
        $result = array();
        foreach ($rows as $row) {
            $object_id = $row['object_id'];
            if (!isset($result[$object_id])) {
                $result[$object_id] = array(
                    'id' => $row['id'],
                    'object_id' => $row['object_id'],
                    'object_name' => $row['object_name'],
                    'object_type' => $row['object_type'],
                    'total_actions' => 0,
                    'verbs' => array()
                );
            }
            if ($row['verb'] !== null) {
                $result[$object_id]['verbs'][$row['verb']] = intval($row['verb_count']);
                $result[$object_id]['total_actions'] += intval($row['verb_count']);
            }
        }
        
        $final_result = array_values($result);
        
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
            "error" => $activity_stmt->errorInfo()
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
