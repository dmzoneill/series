<?php

class Series
{
	private $name = null;
	private $seasons = null;
		
	public function __construct( $series )
	{
		$this->name = $series;
		$this->seasons = array();
	}
	
	public function get_seasons()
	{
		return $this->seasons;
	}
	
	public function get_season( $num )
	{		
		foreach( $this->seasons as $season )
		{					
			if( $season->get_season_num() == $num )
			{				
				return $season;
			}
		}
		
		$this->seasons[ $num ] = new Season( $num );		
		
		return $this->seasons[ $num ];
	}

	public function get_name()
	{
		return $this->name;
	}
}