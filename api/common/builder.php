<?php

function getPaneDetailsPie($storyFragmentId)
{
  $databaseService = new DatabaseService();
  $conn = $databaseService->getConnection();
  $actions_table_name = 'actions';
  $corpus_table_name = 'corpus';
  $parents_table_name = 'parents';

  // initial SQL lookup
  $activity_query = "SELECT p.object_id as paneId, sum((case when a.verb = 'CLICKED' and a.object_id=p.id  then 1 else 0 end)) as clicked,"
    . " sum((case when a.verb = 'READ' and a.object_id=p.id then 1 else 0 end)) as red,"
    . " sum((case when a.verb = 'GLOSSED' and a.object_id=p.id then 1 else 0 end)) as glossed"
    . " from " . $corpus_table_name . " as p left join " . $actions_table_name . " as a on a.object_id=p.id"
    . " left join " . $parents_table_name . " on a.object_id=parents.object_id left join " . $corpus_table_name . " as sf on sf.id=parents.parent_id"
    . " where sf.object_type='storyfragment' AND sf.object_id=:storyfragment_id group by paneId;";
  $activity_stmt = $conn->prepare($activity_query);
  $activity_stmt->bindParam(':storyfragment_id', $storyFragmentId);
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

  $activity_query = "select p.object_id as paneId, p.object_name as title, timestampdiff(hour, max(a.created_at)," .
    " now()) as hours_since_activity," .
    " sum((case when a.verb='CLICKED' and a.object_id=p.id then 1 else 0 end)) as clicked," .
    " sum((case when a.verb='READ' and a.object_id=p.id then 1 else 0 end)) as red," .
    " sum((case when a.verb='GLOSSED' and a.object_id=p.id then 1 else 0 end)) as glossed" .
    " from " . $actions_table_name . " as a left join " . $corpus_table_name . " as p on a.object_id=p.id" .
    " group by paneId order by hours_since_activity;";
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
  $parents_table_name = 'parents';

  // initial SQL lookup
  $activity_query = "
(select sf.object_id as storyFragmentId,sf.object_name as title, timestampdiff(hour, max(a.created_at),now()) as hours_since_activity," .
    " sum((case when a.verb='CLICKED' and a.object_id=p.id then 1 else 0 end)) as clicked," .
    " sum((case when a.verb='READ' and a.object_id=p.id then 1 else 0 end)) as red," .
    " sum((case when a.verb='GLOSSED' and a.object_id=p.id then 1 else 0 end)) as glossed," .
    " sum((case when a.verb='ENTERED' and a.object_id=p.id then 1 else 0 end)) as entered" .
    " from " . $actions_table_name . " as a" .
    " left join " . $corpus_table_name . " as sf on a.object_id=sf.id" .
    " left join " . $corpus_table_name . " as p on a.object_id=p.id" .
    " where sf.object_type='StoryFragment'" .
    " group by storyFragmentId order by hours_since_activity)" .
    " union" .
    " (select  DISTINCT( sf.object_id) as storyFragmentId, sf.object_name as title, timestampdiff(hour, max(a.created_at),now()) as hours_since_activity," .
    " sum((case when a.verb='CLICKED' and a.object_id=p.id then 1 else 0 end)) as clicked," .
    " sum((case when a.verb='READ' and a.object_id=p.id then 1 else 0 end)) as red," .
    " sum((case when a.verb='GLOSSED' and a.object_id=p.id then 1 else 0 end)) as glossed, 0 as entered" .
    " from " . $actions_table_name . " as a" .
    " left join " . $corpus_table_name . " as p on a.object_id=p.id" .
    " left join " . $parents_table_name . " on parents.object_id=p.id" .
    " left join " . $corpus_table_name . " as sf on parents.parent_id=sf.id" .
    " group by storyFragmentId order by hours_since_activity)";
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
