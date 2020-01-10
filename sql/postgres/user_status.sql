DROP SEQUENCE IF EXISTS user_status_us_id_seq CASCADE;
CREATE SEQUENCE user_status_us_id_seq MINVALUE 0 START WITH 0;

CREATE TABLE user_status (
	us_id INTEGER NOT NULL PRIMARY KEY DEFAULT nextval('user_status_us_id_seq'),
	us_actor INTEGER NOT NULL,
	us_sport_id INTEGER NOT NULL default 0,
	us_team_id INTEGER NOT NULL default 0,
	us_text TEXT,
	us_date TIMESTAMPTZ default NULL,
	us_vote_plus INTEGER NOT NULL default 0,
	us_vote_minus INTEGER NOT NULL default 0
);
