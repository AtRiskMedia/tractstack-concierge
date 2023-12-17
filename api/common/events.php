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
  $actions_table_name = 'actions';
  $parents_table_name = 'parents';
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
  //$sql_corpus_parents_ids = [];
  //$parents_known = [];
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
    // is node in corpus table?
    //
    // do look-up of each id in eventStream payload
    foreach ($nodes as $id => $node) {
      array_push($ids, $id);
      if (isset($node->parentId)) array_push($ids, $node->parentId);
    }
    $ids = array_unique($ids);
    $filter = [];
    $idsArray = array_combine(
      array_map(function ($i) {
        return ':id' . $i;
      }, array_keys($ids)),
      $ids
    );
    if (count($idsArray)) {
      $in_placeholders = implode(',', array_keys($idsArray));
      $merged_check_query = "SELECT c.id as sql_id,c.object_id,c.merged FROM " . $corpus_table_name .
        " as c " .
        " WHERE c.object_id IN ( " . $in_placeholders . " )";
      $merged_check_stmt = $conn->prepare($merged_check_query);
      if (!$merged_check_stmt->execute(array_merge($filter, $idsArray))) {
        die();
      } else {
        $rows = $merged_check_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
          // this payload should include all parents themselves as nodes
          $sql_id = isset($row['sql_id']) ? $row['sql_id'] : false;
          $object_id = isset($row['object_id']) ? $row['object_id'] : false;
          $merged = isset($row['merged']) ? $row['merged'] : false;
          if ($sql_id)
            $sql_corpus_ids[$object_id] = $sql_id;
          if ($merged)
            $neo4j_corpus_ids[$object_id] = $merged;
        }
      }
    }
    $neo4j_corpus_ids_merged = $neo4j_corpus_ids;
    $neo4j_corpus_ids = [];

    // second loop through and merge all new nodes to neo4j
    // -- each node may or may not be in sql yet
    // -- merge each node to neo4j, get merged id
    // -- if not in sql, flag for add
    // -- if already in_sql but has additional_parent, flag to add edge in neo4j
    foreach ($nodes as $id => $node) {
      $in_sql = false;
      $parent_in_sql = false;
      if (isset($sql_corpus_ids[$id])) $in_sql = $sql_corpus_ids[$id];
      if (isset($node->parentId, $sql_corpus_ids[$node->parentId]) && !empty($node->parentId)) {
        $parent_in_sql = $sql_corpus_ids[$node->parentId];
        //$thisKey =  $id . '--' . $node->parentId;
        //if (isset($sql_corpus_ids[$thisKey])) 
      } else if (isset($node->parentId) && !empty($node->parentId)) {
        // could be parent is in *this* payload
        // it will be available in $sql_corpus_ids when we need it
        $foundParent = false;
        foreach ($nodes as $innerId => $innerNode) {
          if ($innerId === $node->parentId) $foundParent = true;
        }
        if (!$foundParent) {
          error_log('Parent not found:' . $node->parentId . '  nodes:' . json_encode($nodes) . '       ');
        }
      }
      if (!isset($neo4j_corpus_ids[$id], $node->type, $node->title) && !empty($node->title)) {
        //node + relationship not yet merged to neo4j; must merge
        $thisTitleTrimmed = substr($node->title, 0, 48);
        switch ($node->type) {
          case "Pane":
            $neo4j_object_id = neo4j_merge_corpus($client, $id, $node->title, $node->type);
            $neo4j_corpus_ids[$id] = $neo4j_object_id;
            $thisParent = isset($node->parentId) ? $node->parentId : null;
            $neo4j_merged_corpus_to_update_sql[$id] =
              [$thisTitleTrimmed, $id, $node->type, $thisParent, $neo4j_object_id, $in_sql, $parent_in_sql];
            break;

          case "StoryFragment":
            $neo4j_storyFragment_id = neo4j_merge_storyfragment($client, $id, $node->title);
            $neo4j_corpus_ids[$id] = $neo4j_storyFragment_id;
            $neo4j_merged_corpus_to_update_sql[$id] = [$thisTitleTrimmed, $id, "StoryFragment", $node->parentId, $neo4j_storyFragment_id, $in_sql, $parent_in_sql];
            break;

          case "TractStack":
            $neo4j_tractStack_id = neo4j_merge_tractstack($client, $id, $node->title);
            $neo4j_corpus_ids[$id] = $neo4j_tractStack_id;
            $neo4j_merged_corpus_to_update_sql[$id] = [$thisTitleTrimmed, $id, "TractStack", null, $neo4j_tractStack_id, $in_sql, $parent_in_sql];
            break;

          case "Impression":
            $neo4j_object_id = neo4j_merge_impression($client, $id, $node->title);
            $neo4j_corpus_ids[$id] = $neo4j_object_id;
            $neo4j_merged_corpus_to_update_sql[$id] = [$thisTitleTrimmed, $id, "Impression", $node->parentId, $neo4j_object_id, $in_sql, $parent_in_sql];
            break;

          case "MenuItem":
            $neo4j_object_id = neo4j_merge_menuitem($client, $id, $node->title);
            $neo4j_corpus_ids[$id] = $neo4j_object_id;
            $neo4j_merged_corpus_to_update_sql[$id] = [$thisTitleTrimmed, $id, "MenuItem", $node->parentId, $neo4j_object_id, $in_sql, $parent_in_sql];
            break;

          case "Belief": // for a belief, must also update SQL table 
            $neo4j_object_id = neo4j_merge_belief($client, $id);
            $neo4j_corpus_ids[$id] = $neo4j_object_id;
            $neo4j_merged_corpus_to_update_sql[$id] = [$thisTitleTrimmed, $id, "Belief", $node->parentId, $neo4j_object_id, $in_sql, $parent_in_sql];
            break;

          case "H5P":
            $neo4j_object_id = neo4j_merge_corpus($client, $id, $node->title, $node->type);
            $neo4j_corpus_ids[$id] = $neo4j_object_id;
            $neo4j_merged_corpus_to_update_sql[$id] = [$thisTitleTrimmed, $id, $node->type, $node->parentId, $neo4j_object_id, $in_sql, $parent_in_sql];
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
            $neo4j_storyFragment_id = isset($node->parentId, $neo4j_corpus_ids[$node->parentId]) ? $neo4j_corpus_ids[$node->parentId] : null;
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
      // first loop -- insert into corpus; ignore the parents
      $corpus_merge_query = "INSERT INTO " . $corpus_table_name .
        " (object_name, object_id, object_type, merged )" .
        " VALUES (?,?,?,?)";
      $corpus_merge_stmt = $conn->prepare($corpus_merge_query);
      $conn->beginTransaction();
      foreach ($neo4j_merged_corpus_to_update_sql as $data) {
        $thisObjectName = $data[0];
        $thisObjectId = $data[1];
        $thisObjectType = $data[2];
        $thisObjectNeo4jId = $data[4];
        $thisObjectSqlId = $data[5];
        if ($thisObjectName && $thisObjectId && $thisObjectType && $thisObjectNeo4jId && !$thisObjectSqlId) {
          // insert to corpus
          $thisData = [$thisObjectName, $thisObjectId, $thisObjectType, $thisObjectNeo4jId];
          $corpus_merge_stmt->execute($thisData);
          $sql_id = $conn->lastInsertId();
          if ($sql_id)
            $sql_corpus_ids[$thisObjectId] = $sql_id;
        }
      }
      $conn->commit();
    }

    // second loop -- ensure parents table remains in sync
    if (count($neo4j_merged_corpus_to_update_sql)) {
      foreach ($neo4j_merged_corpus_to_update_sql as $node) {
        if (isset($node[5]))
          array_push($ids, $node[5]);
      }
      $ids = array_unique($ids);
      $filter = [];
      $idsArray = array_combine(
        array_map(function ($i) {
          return ':id' . $i;
        }, array_keys($ids)),
        $ids
      );
      $foundParents = [];
      if (count($idsArray)) {
        $in_placeholders = implode(',', array_keys($idsArray));
        $merged_check_query = "SELECT p.object_id as paneId, sf.object_id as storyFragmentId FROM " . $parents_table_name . " as l" .
          " LEFT JOIN " . $corpus_table_name . " as p ON p.id=l.object_id " .
          " LEFT JOIN " . $corpus_table_name . " as sf ON sf.id=l.parent_id " .
          " WHERE l.object_id IN ( " . $in_placeholders . " )";
        $merged_check_stmt = $conn->prepare($merged_check_query);
        if (!$merged_check_stmt->execute(array_merge($filter, $idsArray))) {
          die();
        } else {
          $rows = $merged_check_stmt->fetchAll(PDO::FETCH_ASSOC);
          foreach ($rows as $row) {
            $paneId = $row['paneId'];
            $storyFragmentId = $row['storyFragmentId'];
            if (isset($paneId, $storyFragmentId))
              $foundParents[$paneId . '--' . $storyFragmentId] = true;
          }
        }
      }
      $parents_merge_query = "INSERT INTO " . $parents_table_name .
        " (object_id, parent_id )" .
        " VALUES (:object_id,:parent_id)";
      $parents_merge_stmt = $conn->prepare($parents_merge_query);
      $conn->beginTransaction();
      foreach ($neo4j_merged_corpus_to_update_sql as $data) {
        if (!isset($data[3])) continue;
        $thisObjectName = $data[0];
        $thisObjectId = $data[1];
        $thisObjectType = $data[2];
        $thisObjectParentId = $data[3];
        $thisObjectNeo4jId = $data[4];
        $thisObjectSqlId = (isset($data[5]) && $data[5] ? $data[5] : isset($sql_corpus_ids[$thisObjectId])) ? $sql_corpus_ids[$thisObjectId] : null;
        if (!$thisObjectSqlId) error_log('no sql id: ' . json_encode($data));
        $thisObjectParentSqlId = $thisObjectParentId ? $sql_corpus_ids[$thisObjectParentId] : null;
        if (
          $thisObjectParentSqlId &&
          !isset($foundParents[$thisObjectId . '--' . $thisObjectParentId])
          && $thisObjectSqlId && $thisObjectParentSqlId
          &&
          isset(
            $thisObjectName,
            $thisObjectId,
            $thisObjectType,
            $thisObjectParentId,
            $thisObjectNeo4jId,
          )
        ) {
          // insert to parents
          $parents_merge_stmt->bindParam(':object_id', $thisObjectSqlId);
          $parents_merge_stmt->bindParam(':parent_id', $thisObjectParentSqlId);
          $parents_merge_stmt->execute();
          $sql_id = $conn->lastInsertId();
        }
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
      $parentId = isset($event->parentId) ? $event->parentId : null;
      $type = isset($event->type) ? $event->type : null;
      $score = isset($event->score) ? $event->score : null;

      if ($type !== "Belief" && isset($verb, $visit_id, $fingerprint_id, $id, $sql_corpus_ids, $sql_corpus_ids[$id])) {
        // save action to table
        $action_merge_query = $verb === 'ENTERED' || (isset($parentId) || $parentId) ? "INSERT INTO " . $actions_table_name .
          " (object_id, visit_id, fingerprint_id, verb,parent_id )" .
          " VALUES (?,?,?,?,?)" : "INSERT INTO " . $actions_table_name .
          " (object_id, visit_id, fingerprint_id, verb )" .
          " VALUES (?,?,?,?)";
        $action_merge_stmt = $conn->prepare($action_merge_query);
        if ($verb === 'ENTERED')
          $thisData = [$sql_corpus_ids[$id], $visit_id, $fingerprint_id, $verb, $sql_corpus_ids[$id]];
        else if (isset($parentId))
          $thisData = [$sql_corpus_ids[$id], $visit_id, $fingerprint_id, $verb, $sql_corpus_ids[$parentId]];
        else $thisData = [$sql_corpus_ids[$id], $visit_id, $fingerprint_id, $verb];
        $action_merge_stmt->execute($thisData);
      }

      switch ($type) {
        case "StoryFragment": // Visit :VERB* Corpus
        case "Pane": // Visit :VERB* Corpus
        case "Context": // Visit :VERB* Corpus
        case "H5P": // Visit :VERB* Corpus
          if (isset($neo4j_corpus_ids[$id]) || isset($neo4j_corpus_ids_merged[$id])) {
            $neo4j_object_id = isset($neo4j_corpus_ids[$id]) ? $neo4j_corpus_ids[$id] : $neo4j_corpus_ids_merged[$id];
            if ($verb === 'CONNECTED' && $parentId) {
              $neo4j_parent_id = isset($neo4j_corpus_ids[$parentId]) ? $neo4j_corpus_ids[$parentId] : $neo4j_corpus_ids_merged[$parentId];
              $statement = neo4j_merge_action($neo4j_parent_id, $neo4j_object_id, $verb, $score);
            } else
              $statement = neo4j_merge_action($neo4j_visit_id, $neo4j_object_id, $verb, $score);
            if ($statement) $actions[] = $statement;
            else error_log('bad on Visit :VERB * ' . $type . "  " . $neo4j_visit_id . "   " . $neo4j_object_id . "  " . $verb . "  " . $score);
          }
          break;

        case "MenuItem": // Visit :Clicks MenuItem
          if (isset($neo4j_corpus_ids[$id]) || isset($neo4j_corpus_ids_merged[$id])) {
            $neo4j_object_id = isset($neo4j_corpus_ids[$id]) ? $neo4j_corpus_ids[$id] : $neo4j_corpus_ids_merged[$id];
            $statement = neo4j_merge_menuitem_action($neo4j_visit_id, $neo4j_object_id);
            if ($statement) $actions[] = $statement;
            else error_log('bad on Visit :CLICKED MenuItem ' . $neo4j_visit_id . "   " . $neo4j_object_id);
          }
          break;

        case "Belief": // Fingerprint :VERB* Belief
          if (isset($sql_corpus_ids[$id]) && (isset($neo4j_corpus_ids[$id]) || isset($neo4j_corpus_ids_merged[$id]))) {
            $has_belief_query = "SELECT verb, object FROM " . $heldbeliefs_table_name . " WHERE fingerprint_id = :fingerprint_id AND belief_id = :belief_id";
            $has_belief_stmt = $conn->prepare($has_belief_query);
            $has_belief_stmt->bindParam(':fingerprint_id', $fingerprint_id);
            $has_belief_stmt->bindParam(':belief_id', $sql_corpus_ids[$id]);
            if ($has_belief_stmt->execute()) {
              $row = $has_belief_stmt->fetch(PDO::FETCH_ASSOC);
              $previous_verb = isset($row['verb']) ? $row['verb'] : false;
              $previous_verb_object = isset($row['object']) ? $row['object'] : false;
            }
            if ($previous_verb && $verb === 'UNSET') {
              // must update SQL and merge Neo4j
              $query = "DELETE FROM " . $heldbeliefs_table_name .
                " WHERE belief_id = :belief_id AND fingerprint_id = :fingerprint_id";
              $stmt = $conn->prepare($query);
              $stmt->bindParam(':belief_id', $sql_corpus_ids[$id]);
              $stmt->bindParam(':fingerprint_id', $fingerprint_id);
              if (!$stmt->execute()) {
                http_response_code(500);
                die();
              }
            } else if ($previous_verb && $previous_verb_object) {
              // must insert SQL and merge Neo4j ** e.g. verb = IDENTIFY_AS; verb doesn't change, but object does
              $query = "UPDATE " . $heldbeliefs_table_name .
                " SET object = :object, updated_at=NOW() WHERE belief_id = :belief_id AND fingerprint_id = :fingerprint_id";
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
                " SET verb = :verb, updated_at=NOW() WHERE belief_id = :belief_id AND fingerprint_id = :fingerprint_id";
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
              $query = "INSERT INTO " . $heldbeliefs_table_name . " SET belief_id = :belief_id, fingerprint_id = :fingerprint_id, verb = :verb, object = :object, updated_at=NOW()";
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
            $neo4j_object_id = isset($neo4j_corpus_ids[$id]) ? $neo4j_corpus_ids[$id] : $neo4j_corpus_ids_merged[$id];
            if ($previous_verb) {
              $statement = neo4j_merge_belief_remove_action($neo4j_fingerprint_id, $neo4j_object_id, $previous_verb, $object);
              if ($statement) $actions[] = $statement;
              else error_log('bad on Remove Fingerprint :VERB* Belief ' . $neo4j_fingerprint_id . "   " . $neo4j_object_id);
            }
            if ($verb !== 'UNSET') {
              $statement = neo4j_merge_belief_action($neo4j_fingerprint_id, $neo4j_object_id, $verb, $object);
              if ($statement) $actions[] = $statement;
              else error_log('bad on Fingerprint :VERB* Belief ' . $neo4j_fingerprint_id . "   " . $neo4j_object_id);
            }
          }
          break;

        case "Impression": // Visit :Clicks Impression
          if (isset($neo4j_corpus_ids[$id]) || isset($neo4j_corpus_ids_merged[$id])) {
            $neo4j_object_id = isset($neo4j_corpus_ids[$id]) ? $neo4j_corpus_ids[$id] : $neo4j_corpus_ids_merged[$id];
            $statement = neo4j_merge_impression_action($neo4j_visit_id, $neo4j_object_id);
            if ($statement) $actions[] = $statement;
            else error_log('bad on Visit :CLICKED Impression ' . $neo4j_visit_id . "   " . $neo4j_object_id);
          }
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
