CREATE TABLE /*_*/user_status_vote (
	-- Unique ID number of the vote, I suppose;
	-- @see UserStatusClass.php, function addStatusVote()
	sv_id int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
	-- Actor ID of the person who voted for the status update
	sv_actor bigint unsigned NOT NULL,
	-- ID of the status update
	sv_us_id int(11) NOT NULL default 0,
	--
	sv_vote_score int(3) NOT NULL default 0,
	-- Timestamp indicating when the vote was cast
	sv_date datetime default null
)/*$wgDBTableOptions*/;
