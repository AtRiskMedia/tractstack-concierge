# Concierge api service for Tract Stack by At Risk Media

_Intelligent no-code websites & landing pages that validate product-market-fit_

- Built using [Gatsby](https://gatsbyjs.com)
- By [At Risk Media](https://atriskmedia.com) with love and oodles of unicorn oomph!


## Tract Stack | Concierge

PHP backend for Tract Stack
- connects to MySQL
- connects to Neo4J

### Install
- be sure to create a .env file under ./api/common and include:
-- DB_HOST, DB_NAME, DB_USER, DB_PASSWORD
-- SECRET_KEY to match gatsby-starter-tractstack
-- SECRET_NEO4J


## MySQL Tables

```
CREATE TABLE leads(
  id INT(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(40) NOT NULL,
  email VARCHAR(20) NOT NULL UNIQUE,
  passwordHash BINARY(16) NOT NULL,
  contact_persona VARCHAR(20) NOT NULL,
  shortBio VARCHAR(280),
  created_at TIMESTAMP NOT NULL,
  updated_at TIMESTAMP NOT NULL,
  merged INT(11) unsigned NOT NULL DEFAULT 0
);

CREATE TABLE corpus(
  id INT(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  created_at TIMESTAMP NOT NULL,
  object_name VARCHAR(48) NOT NULL,
  object_id VARCHAR(36) NOT NULL UNIQUE,
  object_type VARCHAR(40) NOT NULL
);

CREATE TABLE graphmap(
  id INT(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  object_id VARCHAR(36) NOT NULL,
  parent_id VARCHAR(36),
  merged INT(11) unsigned NOT NULL DEFAULT 0,
  CONSTRAINT `fk_corpus` FOREIGN KEY (object_id) REFERENCES corpus (object_id) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_parent` FOREIGN KEY (parent_id) REFERENCES corpus (object_id) ON DELETE CASCADE ON UPDATE RESTRICT
);

CREATE TABLE fingerprints(
  id INT(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  fingerprint VARCHAR(32) NOT NULL UNIQUE,
  created_at TIMESTAMP,
  merged INT(11) unsigned NOT NULL DEFAULT 0,
  lead_id INT(11) unsigned,
  CONSTRAINT `fk_fingerprint_lead` FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE CASCADE ON UPDATE RESTRICT
);

CREATE TABLE visits(
  id INT(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  fingerprint_id INT(11) unsigned NOT NULL,
  created_at TIMESTAMP NOT NULL,
  updated_at TIMESTAMP NOT NULL,
  httpReferrer VARCHAR(4096),
  utmSource VARCHAR(40),
  utmMedium VARCHAR(40),
  utmCampaign VARCHAR(40),
  utmTerm VARCHAR(40),
  utmContent VARCHAR(40),
  httpUserAgent VARCHAR(4096),
  merged INT(11) unsigned NOT NULL DEFAULT 0,
  CONSTRAINT `fk_visit_fingerprint` FOREIGN KEY (fingerprint_id) REFERENCES fingerprints (id) ON DELETE CASCADE ON UPDATE RESTRICT
);

CREATE TABLE tokens(
  id INT(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  fingerprint_id INT(11) unsigned NOT NULL,
  created_at TIMESTAMP NOT NULL,
  updated_at TIMESTAMP NOT NULL,
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
  verb ENUM ('STRONGLY_AGREE','AGREE','NEITHER_AGREE_NOR_DISAGREE','DISAGREE','STRONGLY_DISAGREE','INTERESTED','NOT_INTERESTED','YES','NO','TRUE','FALSE','IDENTIFY_AS') NOT NULL,
  CONSTRAINT `fk_belief_corpus` FOREIGN KEY (belief_id) REFERENCES corpus (id) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_belief_fingerprint` FOREIGN KEY (fingerprint_id) REFERENCES fingerprints (id) ON DELETE CASCADE ON UPDATE RESTRICT
);

```


## neo4j cypher

- leads - node, w/ properties:
-- lead_id
-- first_name
-- last_name
-- company
-- email

- fingerprints - node, w/ properties:
-- fingerprint_id

- beliefs - node, w/ properties:
-- belief_id
-- name

- visits - node, w/ properties:
-- visit_id
-- created_at

- corpus - node, w/ properties:
-- id
-- slug
-- object_type

## relationships:

- leads -> fingerprints ... has
- beliefs -> fingerprints ... held_by
- fingerprints -> visits ... has
- visits -> corpus ... has_read | has_glossedOver / ... has_answered | has_interacted | has_attempted
- corpus -> corpus ... contains
- corpus -> corpus ... points_to

