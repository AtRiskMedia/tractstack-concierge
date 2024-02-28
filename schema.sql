CREATE TABLE campaigns(
  id INT(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(40) NOT NULL,
  merged INT(11) unsigned NOT NULL DEFAULT 0
);

CREATE TABLE leads(
  id INT(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(40) NOT NULL,
  email VARCHAR(20) NOT NULL UNIQUE,
  passwordHash BINARY(16) NOT NULL,
  contact_persona VARCHAR(20) NOT NULL,
  shortBio VARCHAR(280),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  merged INT(11) unsigned NOT NULL DEFAULT 0
);

CREATE TABLE corpus(
  id INT(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  object_name VARCHAR(48) NOT NULL,
  object_id VARCHAR(36) NOT NULL UNIQUE,
  object_type VARCHAR(40) NOT NULL,
  merged INT(11) unsigned NOT NULL DEFAULT 0
);

CREATE TABLE parents(
  id INT(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  object_id INT(11) unsigned,
  parent_id INT(11) unsigned,
  CONSTRAINT `fk_object_id` FOREIGN KEY (object_id) REFERENCES corpus (id) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_parent_id` FOREIGN KEY (parent_id) REFERENCES corpus (id) ON DELETE CASCADE ON UPDATE RESTRICT
);

CREATE TABLE fingerprints(
  id INT(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  fingerprint VARCHAR(32) NOT NULL UNIQUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  merged INT(11) unsigned NOT NULL DEFAULT 0,
  lead_id INT(11) unsigned,
  CONSTRAINT `fk_fingerprint_lead` FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE CASCADE ON UPDATE RESTRICT
);

CREATE TABLE visits(
  id INT(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  fingerprint_id INT(11) unsigned NOT NULL,
  campaign_id INT(11) unsigned,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  utmSource VARCHAR(40),
  utmMedium VARCHAR(40),
  utmTerm VARCHAR(40),
  utmContent VARCHAR(40),
  httpReferrer VARCHAR(4096),
  httpUserAgent VARCHAR(4096),
  merged INT(11) unsigned NOT NULL DEFAULT 0,
  CONSTRAINT `fk_visit_campaign` FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_visit_fingerprint` FOREIGN KEY (fingerprint_id) REFERENCES fingerprints (id) ON DELETE CASCADE ON UPDATE RESTRICT
);

CREATE TABLE tokens(
  id INT(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  fingerprint_id INT(11) unsigned NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  valid_until TIMESTAMP NOT NULL,
  refreshToken BINARY(16) NOT NULL,
  CONSTRAINT `fk_token_fingerprint` FOREIGN KEY (fingerprint_id) REFERENCES fingerprints (id) ON DELETE CASCADE ON UPDATE RESTRICT
);

CREATE TABLE heldbeliefs(
  id INT(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  belief_id INT(11) unsigned NOT NULL,
  fingerprint_id INT(11) unsigned NOT NULL,
  object_type VARCHAR(40) NOT NULL DEFAULT 'Belief',
  object VARCHAR(40),
  verb ENUM ('STRONGLY_AGREES','AGREES','NEITHER_AGREES_NOR_DISAGREES','DISAGREES','STRONGLY_DISAGREES','INTERESTED','NOT_INTERESTED','BELIEVES_YES','BELIEVES_NO','BELIEVES_TRUE','BELIEVES_FALSE','IDENTIFY_AS') NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_belief_corpus` FOREIGN KEY (belief_id) REFERENCES corpus (id) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_belief_fingerprint` FOREIGN KEY (fingerprint_id) REFERENCES fingerprints (id) ON DELETE CASCADE ON UPDATE RESTRICT
);

CREATE TABLE actions(
  id INT(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  object_id INT(11) unsigned NOT NULL,
  parent_id INT(11) unsigned,
  fingerprint_id INT(11) unsigned NOT NULL,
  visit_id INT(11) unsigned NOT NULL,
  verb VARCHAR(40),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_actions_object_id` FOREIGN KEY (object_id) REFERENCES corpus (id) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_actions_parent_id` FOREIGN KEY (parent_id) REFERENCES corpus (id) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_action_fingerprint` FOREIGN KEY (fingerprint_id) REFERENCES fingerprints (id) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_action_visit` FOREIGN KEY (visit_id) REFERENCES visits (id) ON DELETE CASCADE ON UPDATE RESTRICT
);
