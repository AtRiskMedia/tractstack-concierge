<?php

function processEventStream($jwt, $payload)
{
  // neo4j
  $client = neo4j_connect();
  // for SQL
  $databaseService = new DatabaseService();
  $conn = $databaseService->getConnection();
  $visits_table_name = 'visits';
  $fingerprints_table_name = 'fingerprints';
  $corpus_table_name = 'corpus';
  $graphmap_table_name = 'graphmap';
  $heldbeliefs_table_name = 'heldbeliefs';

  // get jwt
  $fingerprint_id = isset($jwt->fingerprint_id) ? $jwt->fingerprint_id : false;
  $visit_id = isset($jwt->visit_id) ? $jwt->visit_id : false;
  //$created_at = isset($jwt->created_at) ? $jwt->created_at : false;

  // defaults
  $actions = [];
  $neo4j_visit_id = false;
  $neo4j_object_id = false;
  $neo4j_parent_id = false;
  $sql_id = false;
  $ids = [];
  $neo4j_corpus_ids = [];
  $sql_corpus_ids = [];
  $sql_corpus_graphmap_ids = [];
  $graphmap_known = [];
  $neo4j_merged_corpus_to_update_sql = [];

  // first get neo4j id for visit
  if ($fingerprint_id && $visit_id) {
    $is_merged_query = "SELECT v.merged as visits_merged, f.merged as fingerprint_merged FROM " . $visits_table_name . " v LEFT JOIN " . $fingerprints_table_name . " f ON v.fingerprint_id = f.id WHERE v.id = :visit_id";
    $is_merged_stmt = $conn->prepare($is_merged_query);
    $is_merged_stmt->bindParam(':visit_id', $visit_id);
    if ($is_merged_stmt->execute()) {
      $row = $is_merged_stmt->fetch(PDO::FETCH_ASSOC);
      $neo4j_visit_id = isset($row['visits_merged']) ? $row['visits_merged'] : false;
      $neo4j_fingerprint_id = isset($row['fingerprint_merged']) ? $row['fingerprint_merged'] : false;
    } else {
      die();
    }
  }
  if (!$neo4j_visit_id && !$neo4j_fingerprint_id) {
    // visit has not yet been merged; something is wrong
    error_log('this visit has not been merged to neo4j; something is wrong');
    return (401);
  }

  // pass through payload nodes to find all unmerged nodes
  // then pass through events payload to apply relationship from visit
  $events = $payload->events;
  $nodes = $payload->nodes;

  if (isset($nodes)) {
    // check each node in this payload
    // -- is it in corpus table? is it in graphmap table and merged to neo4j?
    foreach ($nodes as $id => $node) {
      array_push($ids, $id);
    }
    $ids = array_unique($ids);
    $filter = [];
    $idsArray = array_combine(
      array_map(function ($i) {
        return ':id' . $i;
      }, array_keys($ids)),
      $ids
    );
    $in_placeholders = implode(',', array_keys($idsArray));
    $merged_check_query = "SELECT c.id as sql_id,c.object_id,g.merged, g.parent_id as parent_id FROM " . $corpus_table_name .
      " as c LEFT JOIN " . $graphmap_table_name . " as g ON " .
      " c.object_id = g.object_id " .
      " WHERE c.object_id IN ( " . $in_placeholders . " )";
    $merged_check_stmt = $conn->prepare($merged_check_query);
    if (!$merged_check_stmt->execute(array_merge($filter, $idsArray))) {
      die();
    } else {
      $rows = $merged_check_stmt->fetchAll();
      foreach ($rows as $row) {
        // this may include duplicates ... due to many to one of graphmap
        $sql_id = isset($row['sql_id']) ? $row['sql_id'] : false;
        $object_id = isset($row['object_id']) ? $row['object_id'] : false;
        $parent_id = isset($row['parent_id']) ? $row['parent_id'] : false;
        $merged = isset($row['merged']) ? $row['merged'] : false;
        if ($object_id && $parent_id && $merged) {
          $key = $object_id . '--' . $parent_id;
          $graphmap_known[$key] = true;
        }
        $thisNode = isset($nodes->$object_id) ? $nodes->$object_id : null;
        $thisNodeParent = isset($thisNode->parentId) ? $thisNode->parentId : null;
        if ($sql_id) {
          if ($parent_id && $thisNodeParent !== $parent_id) {
            // already in SQL but with different parentId
            $sql_corpus_ids[$object_id] = $sql_id;
            $sql_corpus_graphmap_ids[$object_id] = $sql_id;
          } else
            // already in SQL
            $sql_corpus_ids[$object_id] = $sql_id;
        }
        if ($merged)
          // already in Neo4j
          $neo4j_corpus_ids[$object_id] = $merged;
      }
    }
    $neo4j_corpus_ids_merged = $neo4j_corpus_ids;
    $neo4j_corpus_ids = [];

    // second loop through and merge all new nodes to neo4j
    // -- each node may or may not be in sql
    // -- merge each node to neo4j, get merged id
    // -- if not in sql, flag for add
    // -- if already in_sql but has additional_parent, flag to add edge in neo4j
    foreach ($nodes as $id => $node) {
      $in_sql = false;
      $additional_parent = false;
      if (isset($sql_corpus_ids[$id])) $in_sql = true;
      if (isset($sql_corpus_graphmap_ids[$id])) $additional_parent = true;

      if (!isset($neo4j_corpus_ids[$id]) && isset($node->type)) {
        $thisTitleTrimmed = substr($node->title, 0, 48);
        switch ($node->type) {
          case "Pane":
            $neo4j_object_id = neo4j_merge_corpus($client, $id, $node->title, $node->type);
            $neo4j_corpus_ids[$id] = $neo4j_object_id;
            $neo4j_merged_corpus_to_update_sql[$id] = [$thisTitleTrimmed, $id, $node->type, $node->parentId, $neo4j_object_id, $in_sql, $additional_parent];
            break;

          case "StoryFragment":
            $neo4j_storyFragment_id = neo4j_merge_storyfragment($client, $id, $node->title);
            $neo4j_corpus_ids[$id] = $neo4j_storyFragment_id;
            $neo4j_merged_corpus_to_update_sql[$id] = [$thisTitleTrimmed, $id, "StoryFragment", $node->parentId, $neo4j_storyFragment_id, $in_sql, $additional_parent];
            break;

          case "TractStack":
            $neo4j_tractStack_id = neo4j_merge_tractstack($client, $id, $node->title);
            $neo4j_corpus_ids[$id] = $neo4j_tractStack_id;
            $neo4j_merged_corpus_to_update_sql[$id] = [$thisTitleTrimmed, $id, "TractStack", null, $neo4j_tractStack_id, $in_sql, $additional_parent];
            break;

          case "Impression":
            $neo4j_object_id = neo4j_merge_impression($client, $id, $node->title);
            $neo4j_corpus_ids[$id] = $neo4j_object_id;
            $neo4j_merged_corpus_to_update_sql[$id] = [$thisTitleTrimmed, $id, "Impression", $node->parentId, $neo4j_object_id, $in_sql, $additional_parent];
            break;

          case "MenuItem":
            $neo4j_object_id = neo4j_merge_menuitem($client, $id, $node->title);
            $neo4j_corpus_ids[$id] = $neo4j_object_id;
            $neo4j_merged_corpus_to_update_sql[$id] = [$thisTitleTrimmed, $id, "MenuItem", $node->parentId, $neo4j_object_id, $in_sql, $additional_parent];
            break;

          case "Belief": // for a belief, must also update SQL table 
            $neo4j_object_id = neo4j_merge_belief($client, $id, $node->title);
            $neo4j_corpus_ids[$id] = $neo4j_object_id;
            $neo4j_merged_corpus_to_update_sql[$id] = [$thisTitleTrimmed, $id, "Belief", $node->parentId, $neo4j_object_id, $in_sql, $additional_parent];
            break;

          case "H5P":
            $neo4j_object_id = neo4j_merge_corpus($client, $id, $node->title, $node->type);
            $neo4j_corpus_ids[$id] = $neo4j_object_id;
            $neo4j_merged_corpus_to_update_sql[$id] = [$thisTitleTrimmed, $id, $node->type, $node->parentId, $neo4j_object_id, $in_sql, $additional_parent];
            break;
        }
      }
    }

    // loop through and merge relationships between all new nodes
    foreach ($nodes as $id => $node) {
      // for corpus beloging to multiple parents, must detect:
      $special = isset($node->type) && isset($neo4j_corpus_ids[$id]) && isset($neo4j_corpus_ids_merged[$id]) && $neo4j_corpus_ids[$id] === $neo4j_corpus_ids_merged[$id] ? true : false;
      if ($special || (!isset($neo4j_corpus_ids_merged[$id]) && isset($neo4j_corpus_ids[$id]) && isset($node->type))) {
        switch ($node->type) {
          case "Pane": // StoryFragment :CONTAINS Pane ... or TractStack :CONTAINS Pane if context
            $neo4j_object_id = $neo4j_corpus_ids[$id];
            $neo4j_storyFragment_id = $neo4j_corpus_ids[$node->parentId];
            if ($neo4j_object_id && $neo4j_storyFragment_id) {
              $statement = neo4j_storyFragment_contains_corpus($neo4j_storyFragment_id, $neo4j_object_id);
              if ($statement) $actions[] = $statement;
              else error_log('bad on StoryFragment :CONTAINS Pane' . "  " . $neo4j_object_id . "   " . $neo4j_storyFragment_id);
            }
            break;

          case "StoryFragment": // TractStack :CONTAINS StoryFragment
            $neo4j_storyFragment_id = $neo4j_corpus_ids[$id];
            $neo4j_tractStack_id = $neo4j_corpus_ids[$node->parentId];
            if ($neo4j_tractStack_id && $neo4j_storyFragment_id) {
              $statement = neo4j_tractStack_contains_storyFragment($neo4j_tractStack_id, $neo4j_storyFragment_id);
              if ($statement) $actions[] = $statement;
              else error_log('bad on TractStack :CONTAINS StoryFragment' . "  " . $neo4j_tractStack_id . "   " . $neo4j_storyFragment_id);
            }
            break;

          case "TractStack":
            // nothing to do
            break;

          case "MenuItem": // MenuItems :LINKS StoryFragment
            $neo4j_object_id = $neo4j_corpus_ids[$id];
            $neo4j_storyFragment_id = $neo4j_corpus_ids[$node->parentId];
            if ($neo4j_object_id && $neo4j_storyFragment_id) {
              $statement = neo4j_menuitem_links_storyFragment($neo4j_object_id, $neo4j_storyFragment_id);
              if ($statement) $actions[] = $statement;
              else error_log('bad on MenuItem :LINKS StoryFragment' . "  " . $neo4j_object_id . "   " . $neo4j_storyFragment_id);
            }
            break;

          case "Belief": // TractStack :CONTAINS Belief
            $neo4j_object_id = $neo4j_corpus_ids[$id];
            $neo4j_tractStack_id = $neo4j_corpus_ids[$node->parentId];
            if ($neo4j_tractStack_id && $neo4j_object_id) {
              $statement = neo4j_tractStack_contains_belief($neo4j_tractStack_id, $neo4j_object_id);
              if ($statement) $actions[] = $statement;
              else error_log('bad on TractStack :CONTAINS Belief  ' . $neo4j_tractStack_id . "   " . $neo4j_object_id);
            }
            break;

          case "H5P": // Pane :CONTAINS H5P
            $neo4j_object_id = $neo4j_corpus_ids[$id];
            $neo4j_parent_id = $neo4j_corpus_ids[$node->parentId];
            if ($neo4j_object_id && $neo4j_parent_id) {
              $statement = neo4j_corpus_contains_corpus($neo4j_parent_id, $neo4j_object_id);
              if ($statement) $actions[] = $statement;
              else error_log('bad on Pane contains H5P ' . $neo4j_object_id . "   " . $neo4j_parent_id);
            }
            break;

          case "Impression": // Pane :CONTAINS Impression
            $neo4j_object_id = $neo4j_corpus_ids[$id];
            $neo4j_parent_id = $neo4j_corpus_ids[$node->parentId];
            if ($neo4j_object_id && $neo4j_parent_id) {
              $statement = neo4j_corpus_contains_impression($neo4j_parent_id, $neo4j_object_id);
              if ($statement) $actions[] = $statement;
              else error_log('bad on Pane contains Impression '  . $neo4j_object_id . "   " . $neo4j_parent_id);
            }
            break;
        }
      }
    }

    if (count($neo4j_merged_corpus_to_update_sql)) {
      $graphmap_to_update_sql = [];
      // first store to corpus table
      $corpus_merge_query = "INSERT INTO " . $corpus_table_name .
        " (object_name, object_id, object_type )" .
        " VALUES (?,?,?)";
      $corpus_merge_stmt = $conn->prepare($corpus_merge_query);
      $conn->beginTransaction();
      foreach ($neo4j_merged_corpus_to_update_sql as $data) {
        // data = [ 0:object_name, 1:object_id, 2:object_type, 3:parent_id, 4:neo4j merged_id, 5:in_sql, 6:additional_parent]
        if ($data[1] && $data[2] && $data[4] && !$data[5] && !$data[6]) { // if not already in sql
          $thisData = [$data[0], $data[1], $data[2]];
          $corpus_merge_stmt->execute($thisData);
          $sql_id = $conn->lastInsertId();
          if ($sql_id) {
            $graphmap_to_update_sql[] = [$data[1], $data[3], $data[4]];
            $sql_corpus_ids[$data[1]] = $sql_id;
          }
        } else if ($data[1] && $data[2] && $data[4] && $data[5] && $data[6]) { // other parent already in sql, not this new parent
          $key = $data[1] . '--' . $data[3];
          if (!isset($graphmap_known[$key]))
            // object already in sql, only update graphmap
            $graphmap_to_update_sql[] = [$data[1], $data[3], $data[4]];
        } else if (!$data[5]) {
          error_log(' MISS ' . json_encode($data));
        }
      }
      $conn->commit();

      // then update graphmap with merged graph ids
      $graphmap_merge_query = "INSERT INTO " . $graphmap_table_name .
        " (object_id, parent_id, merged )" .
        " VALUES (?,?,?)";
      $graphmap_merge_stmt = $conn->prepare($graphmap_merge_query);
      $conn->beginTransaction();
      foreach ($graphmap_to_update_sql as $data) {
        // data = [ 0:object_id, 1:parent_id, 2:merged ]
        $graphmap_merge_stmt->execute($data);
        $conn->lastInsertId();
      }
      $conn->commit();
    }
  }

  if (isset($events)) {
    foreach ($events as $event) {
      // process eventStream payload; merge to graph (if not set); add relationship
      $verb = isset($event->verb) ? str_replace(' ', '_', strtoupper($event->verb)) : null;
      $object = isset($event->object) ? str_replace(' ', '_', strtoupper($event->object)) : null;
      $previous_verb = null;
      $previous_verb_object = null;
      $id = isset($event->id) ? $event->id : null;
      $type = isset($event->type) ? $event->type : null;
      $score = isset($event->score) ? $event->score : null;
      switch ($type) {
        case "Pane": // Visit :VERB* Corpus
        case "Context": // Visit :VERB* Corpus
        case "H5P": // Visit :VERB* Corpus
          if (isset($neo4j_corpus_ids[$id])) {
            $neo4j_object_id = $neo4j_corpus_ids[$id];
            $statement = neo4j_merge_action($neo4j_visit_id, $neo4j_object_id, $verb, $score);
            if ($statement) $actions[] = $statement;
            else error_log('bad on Visit :VERB* ' . $type . "  " . $neo4j_visit_id . "   " . $neo4j_object_id . "  " . $verb . "  " . $score);
          } else
            error_log('bad pch? ' . $id . " " . json_encode($payload));
          break;

        case "MenuItem": // Visit :Clicks MenuItem
          if (isset($neo4j_corpus_ids[$id])) {
            $neo4j_object_id = $neo4j_corpus_ids[$id];
            $statement = neo4j_merge_menuitem_action($neo4j_visit_id, $neo4j_object_id);
            if ($statement) $actions[] = $statement;
            else error_log('bad on Visit :CLICKED MenuItem ' . $neo4j_visit_id . "   " . $neo4j_object_id);
          } else
            error_log('bad mi ' . $id . " " . json_encode($payload));
          break;

        case "Belief": // Visit :VERB* Belief
          if (isset($neo4j_corpus_ids[$id], $sql_corpus_ids[$id])) {
            $has_belief_query = "SELECT verb, object FROM " . $heldbeliefs_table_name . " WHERE fingerprint_id = :fingerprint_id AND belief_id = :belief_id";
            $has_belief_stmt = $conn->prepare($has_belief_query);
            $has_belief_stmt->bindParam(':fingerprint_id', $fingerprint_id);
            $has_belief_stmt->bindParam(':belief_id', $sql_corpus_ids[$id]);
            if ($has_belief_stmt->execute()) {
              $row = $has_belief_stmt->fetch(PDO::FETCH_ASSOC);
              $previous_verb = isset($row['verb']) ? $row['verb'] : false;
              $previous_verb_object = isset($row['object']) ? $row['object'] : false;
            }
            if ($previous_verb && $previous_verb_object) {
              // must insert SQL and merge Neo4j ** e.g. verb = IDENTIFY_AS; verb doesn't change, but object does
              $query = "UPDATE " . $heldbeliefs_table_name .
                " SET object = :object WHERE belief_id = :belief_id AND fingerprint_id = :fingerprint_id";
              $stmt = $conn->prepare($query);
              $stmt->bindParam(':belief_id', $sql_corpus_ids[$id]);
              $stmt->bindParam(':fingerprint_id', $fingerprint_id);
              $stmt->bindParam(':object', $object);
              if (!$stmt->execute()) {
                http_response_code(500);
                die();
              }
            } else if ($previous_verb) {
              // must update SQL and merge Neo4j
              $query = "UPDATE " . $heldbeliefs_table_name .
                " SET verb = :verb WHERE belief_id = :belief_id AND fingerprint_id = :fingerprint_id";
              $stmt = $conn->prepare($query);
              $stmt->bindParam(':belief_id', $sql_corpus_ids[$id]);
              $stmt->bindParam(':fingerprint_id', $fingerprint_id);
              $stmt->bindParam(':verb', $verb);
              if (!$stmt->execute()) {
                http_response_code(500);
                die();
              }
            } else {
              // must insert into SQL and push to Neo4j
              $query = "INSERT INTO " . $heldbeliefs_table_name . " SET belief_id = :belief_id, fingerprint_id = :fingerprint_id, verb = :verb, object = :object";
              $stmt = $conn->prepare($query);
              $stmt->bindParam(':belief_id', $sql_corpus_ids[$id]);
              $stmt->bindParam(':fingerprint_id', $fingerprint_id);
              $stmt->bindParam(':verb', $verb);
              $stmt->bindParam(':object', $object);
              if (!$stmt->execute()) {
                http_response_code(500);
                die();
              }
            }
            // now merge to neo4j
            $neo4j_object_id = $neo4j_corpus_ids[$id];
            if ($previous_verb) {
              $statement = neo4j_merge_belief_remove_action($neo4j_visit_id, $neo4j_object_id, $previous_verb, $object);
              if ($statement) $actions[] = $statement;
            }
            $statement = neo4j_merge_belief_action($neo4j_visit_id, $neo4j_object_id, $verb, $object);
            if ($statement) $actions[] = $statement;
            else error_log('bad on Visit :VERB* Belief ' . $neo4j_visit_id . "   " . $neo4j_object_id);
          } else
            error_log('bad b ' . $id . " " . json_encode($payload));
          break;

        case "Impression": // Visit :Clicks Impression
          if (isset($neo4j_corpus_ids[$id])) {
            $neo4j_object_id = $neo4j_corpus_ids[$id];
            $statement = neo4j_merge_impression_action($neo4j_visit_id, $neo4j_object_id);
            if ($statement) $actions[] = $statement;
            else error_log('bad on Visit :CLICKED Impression ' . $neo4j_visit_id . "   " . $neo4j_object_id);
          } else
            error_log('bad i ' . $id . " " . json_encode($payload));
          break;

        default:
          error_log("MISSED ON " . $type);
          break;
      }
    }
  }

  // now merge graph links
  if ($actions)
    $client->runStatements($actions);
}
