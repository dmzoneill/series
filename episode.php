<?php

class Episode
{
	private $num = null;
	private $name = null; 
		
	public function __construct( $arr )
	{
		$this->num = $arr[0];
		$this->name = $arr[1];
	}

	public function get_episode_num()
	{
		return $this->num;
	}
	
	public function get_episode_name()
	{
		return $this->name;
	}
}
