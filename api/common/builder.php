<?php

function getPaneDetailsPie($storyFragmentId)
{
  $databaseService = new DatabaseService();
  $conn = $databaseService->getConnection();
  $actions_table_name = 'actions';
  $corpus_table_name = 'corpus';
  $parents_table_name = 'parents';

  // initial SQL lookup
  $activity_query = "SELECT DISTINCT(c.object_id) as paneId, sum((case when a.verb = 'CLICKED' then 1 else 0 end)) AS clicked, 
sum((case when a.verb = 'READ' then 1 else 0 end)) as red, sum((case when a.verb = 'GLOSSED' then 1 else 0 end)) AS glossed 
FROM " . $actions_table_name . " AS a LEFT JOIN corpus as c on a.object_id=c.id WHERE a.object_id IN (select DISTINCT( p.object_id ) AS ids FROM " . $parents_table_name . " AS p LEFT JOIN corpus AS pc on c.id=p.parent_id
 LEFT JOIN " . $actions_table_name . " AS a on p.object_id = a.object_id WHERE p.parent_id IN (select c.id from " . $corpus_table_name . " as c where c.object_id= :storyfragment_id ))
 GROUP BY a.object_id";
  $activity_stmt = $conn->prepare($activity_query);
  $activity_stmt->bindParam(':storyfragment_id', $storyFragmentId);
  if ($activity_stmt->execute()) {
    $rows = $activity_stmt->fetchAll();
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
