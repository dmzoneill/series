<?php

class Season
{
	private $num = null;
	private $episodes = null;
		
	public function __construct( $num )
	{
		$this->num = $num;
		$this->episodes = array();
	}	
	
	public function get_season_num()
	{
		return $this->num;
	}
	
	public function add_episode( $episode )
	{
		$this->episodes[] = $episode;
	}
	
	public function get_episodes()
	{
		return $this->episodes;
	}
}
