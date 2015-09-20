<?php

class EztvWorker extends Thread
{
	private $working = false;
	private $series = null;
	private $seriesid = 0;
	private $html = null;
	private $processed = false;	
	
	public function __construct( $arr )
	{
		$this->series = $arr[0];
		$this->seriesid = $arr[1];
	}

	public function __destruct()
	{
		$this->working = null;
		$this->series = null;
		$this->seriesid = null;
		$this->html = null;
		$this->processed = null;	
	}
	
	public function run()
	{		
		$this->working = true;
		$this->get_series_data();		
		$this->working = false;	
	}
	
	public function is_working()
	{
		return $this->working;
	}
	
	public function get_html()
	{
		$this->processed = true;
		return $this->html;
	}

	public function get_series_name()
	{
		return $this->series;
	}

	public function is_processed()
	{
		return $this->processed;
	}
	
	private function get_series_data()
	{		
		$temp_file = dirname( __FILE__ ) . "/eztv/" . $this->seriesid;
		$temp_file = str_replace( '\\', '/', $temp_file ); 

		if( file_exists( $temp_file ) )
		{
			if( filemtime( $temp_file ) + 3600 > time() )
			{
				$this->html = file_get_contents( $temp_file );
				return;
			}
			unlink( $temp_file ); 
		}

		$temp = file_get_contents( 'https://eztv.ag/shows/' . $this->seriesid . '/' . rawurlencode( $this->series ) .'/' );	

		if( !file_exists( "eztv/" ) )
			mkdir( "eztv" );

		$temp = explode( "Episode Name", $temp );
		$temp = explode( "</table>", $temp[1] );
				
		if( file_put_contents( $temp_file, $temp[0] ) === false )
			return;
		
		$this->html = $temp[0];		
	}
}