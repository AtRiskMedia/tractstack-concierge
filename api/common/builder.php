<?php

function getPaneDetailsPie($storyFragmentId)
{
  $databaseService = new DatabaseService();
  $conn = $databaseService->getConnection();
  $actions_table_name = 'actions';
  $corpus_table_name = 'corpus';

  // initial SQL lookup
  $activity_query = "SELECT p.object_id as paneId, sum((case when a.verb = 'CLICKED' then 1 else 0 end)) as clicked,"
    . " sum((case when a.verb = 'READ' then 1 else 0 end)) as red,"
    . " sum((case when a.verb = 'GLOSSED' then 1 else 0 end)) as glossed"
    . " from " . $actions_table_name . " as a"
    . " left join " . $corpus_table_name . " as p on a.object_id=p.id"
    . " left join " . $corpus_table_name . " as sf on a.parent_id=sf.id"
    . " where sf.object_id=:storyFragmentId"
    . " group by paneId;";
  $activity_stmt = $conn->prepare($activity_query);
  $activity_stmt->bindParam(':storyFragmentId', $storyFragmentId);
  if ($activity_stmt->execute()) {
    $rows = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(array(
      "data" => json_encode($rows),
      "message" => "Success.",
      "error" => null
    ));
    return (200);
  } else {
    return (500);
  }
  echo json_encode(array(
    "data" => null,
    "message" => "Success.",
    "error" => null
  ));
  return (200);
}


function getPaneActivitySwarm()
{
  $databaseService = new DatabaseService();
  $conn = $databaseService->getConnection();
  $actions_table_name = 'actions';
  $corpus_table_name = 'corpus';

  // initial SQL lookup
  $activity_query = "select p.object_id as paneId, p.object_name as title," .
    " timestampdiff(hour, max(a.created_at),now()) as hours_since_activity," .
    " sum((case when a.verb='CLICKED' and a.object_id=p.id then 1 else 0 end)) as clicked," .
    " sum((case when a.verb='READ' and a.object_id=p.id then 1 else 0 end)) as red," .
    " sum((case when a.verb='GLOSSED' and a.object_id=p.id then 1 else 0 end)) as glossed" .
    " from " . $actions_table_name . " as a" .
    " left join " . $corpus_table_name . " as p on a.object_id=p.id" .
    " where p.object_type='Pane'" .
    " group by paneId;";
  $activity_stmt = $conn->prepare($activity_query);
  if ($activity_stmt->execute()) {
    $rows = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(array(
      "data" => json_encode($rows),
      "message" => "Success.",
      "error" => null
    ));
    return (200);
  } else {
    return (500);
  }
  echo json_encode(array(
    "data" => null,
    "message" => "Success.",
    "error" => null
  ));
  return (200);
}


function getStoryFragmentActivitySwarm()
{
  $databaseService = new DatabaseService();
  $conn = $databaseService->getConnection();
  $actions_table_name = 'actions';
  $corpus_table_name = 'corpus';

  // initial SQL lookup
  $activity_query = "select sf.object_id as storyFragmentId, sf.object_name as title," .
    " timestampdiff(hour, max(a.created_at),now()) as hours_since_activity," .
    " sum((case when a.verb='CLICKED' and a.object_id=p.id then 1 else 0 end)) as clicked," .
    " sum((case when a.verb='READ' and a.object_id=p.id then 1 else 0 end)) as red," .
    " sum((case when a.verb='GLOSSED' and a.object_id=p.id then 1 else 0 end)) as glossed," .
    " sum((case when a.verb='ENTERED' and a.object_id=sf.id then 1 else 0 end)) as entered from " . $actions_table_name . " as a" .
    " left join " . $corpus_table_name . " as sf on a.parent_id=sf.id left join " . $corpus_table_name . " as p on a.object_id=p.id" .
    " where sf.object_type='StoryFragment' group by storyFragmentId;";
  $activity_stmt = $conn->prepare($activity_query);
  if ($activity_stmt->execute()) {
    $rows = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(array(
      "data" => json_encode($rows),
      "message" => "Success.",
      "error" => null
    ));
    return (200);
  } else {
    return (500);
  }
  echo json_encode(array(
    "data" => null,
    "message" => "Success.",
    "error" => null
  ));
  return (200);
}

function getStoryFragmentDaysSince()
{
  $databaseService = new DatabaseService();
  $conn = $databaseService->getConnection();
  $actions_table_name = 'actions';
  $corpus_table_name = 'corpus';

  // initial SQL lookup
  $activity_query = "select sf.object_id as storyFragmentId, sf.object_name as title," .
    " timestampdiff(hour, max(a.created_at),now()) as hours_since_activity" .
    " from " . $actions_table_name . " as a" .
    " left join " . $corpus_table_name . " as sf on a.parent_id=sf.id" .
    " group by storyFragmentId;";
  $activity_stmt = $conn->prepare($activity_query);
  if ($activity_stmt->execute()) {
    $rows = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(array(
      "data" => json_encode($rows),
      "message" => "Success.",
      "error" => null
    ));
    return (200);
  } else {
    return (500);
  }
  echo json_encode(array(
    "data" => null,
    "message" => "Success.",
    "error" => null
  ));
  return (200);
}


function getPanesDaysSince()
{
  $databaseService = new DatabaseService();
  $conn = $databaseService->getConnection();
  $actions_table_name = 'actions';
  $corpus_table_name = 'corpus';

  // initial SQL lookup
  $activity_query = "select p.object_id as paneId, p.object_name as title," .
    " timestampdiff(hour, max(a.created_at),now()) as hours_since_activity" .
    " from " . $actions_table_name . " as a" .
    " left join " . $corpus_table_name . " as p on a.object_id=p.id" .
    " group by paneId;";
  $activity_stmt = $conn->prepare($activity_query);
  if ($activity_stmt->execute()) {
    $rows = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(array(
      "data" => json_encode($rows),
      "message" => "Success.",
      "error" => null
    ));
    return (200);
  } else {
    return (500);
  }
  echo json_encode(array(
    "data" => null,
    "message" => "Success.",
    "error" => null
  ));
  return (200);
}
