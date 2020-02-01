DROP SEQUENCE IF EXISTS user_status_vote_sv_id_seq CASCADE;
CREATE SEQUENCE user_status_vote_sv_id_seq;

CREATE TABLE user_status_vote (
	sv_id INTEGER NOT NULL PRIMARY KEY DEFAULT nextval('user_status_vote_sv_id_seq'),
	sv_actor INTEGER NOT NULL,
	sv_us_id INTEGER NOT NULL default 0,
	sv_vote_score SMALLINT NOT NULL default 0,
	sv_date TIMESTAMPTZ default null
);

ALTER SEQUENCE user_status_vote_sv_id_seq OWNED BY user_status_vote.sv_id;
