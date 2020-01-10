CREATE TABLE /*_*/user_status (
	-- Unique ID number of the status update
	us_id int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
	-- Actor ID of the person who posted this status update
	us_actor bigint unsigned NOT NULL,
	-- Sports ID (see the SportsTeams extension and its tables) that is
	-- associated with this status update
	us_sport_id int(11) NOT NULL default 0,
	-- See above, except that this is an individual sports team (i.e.
	-- New York Mets)
	us_team_id int(11) NOT NULL default 0,
	-- Actual text of the status update
	us_text text,
	-- Timestamp indicating when the status update was posted
	us_date datetime default null,
	-- How many up/plus votes the status update has?
	us_vote_plus int(11) NOT NULL default 0,
	-- How many down/minus votes the status update has?
	us_vote_minus int(11) NOT NULL default 0
)/*$wgDBTableOptions*/;
