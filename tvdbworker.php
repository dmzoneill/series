<?php

class TvdbWorker extends Thread
{
	private $working = false;
	private $series = null;
	private $seriesid = 0;
	private $xml = null;
	private $processed = false;	
	
	public function __construct( $series )
	{
		$this->series = $series;
	}

	public function __destruct()
	{
		$this->working = null;
		$this->series = null;
		$this->seriesid = null;
		$this->xml = null;
		$this->processed = null;	
	}
	
	public function run()
	{		
		$this->working = true;
		$this->get_series_id();
		
		if( $this->seriesid > 0 )
		{
			$this->get_series_data();
		}
		
		$this->working = false;	
	}
	
	public function is_working()
	{
		return $this->working;
	}
	
	public function get_xml()
	{
		$this->processed = true;
		return $this->xml;
	}
	
	public function is_processed()
	{
		return $this->processed;
	}
	
	public function get_series()
	{
		return $this->series;
	}
	
	private function get_series_id()
	{		
		$xml = file_get_contents( 'http://thetvdb.com/api/GetSeries.php?seriesname=' . rawurlencode( $this->series ) );
								
		$tree = new SimpleXMLElement( $xml );
						
		if( $tree != false )
		{
			$ids = $tree->xpath( './/seriesid' );
			$names = $tree->xpath( './/SeriesName' );
			
			for( $x = 0; $x < count( $ids ); $x++ )
			{			
				$xmlname = trim( strtolower( $names[ $x ] ) );
				$searchname = trim( strtolower( $this->series ) );
								
				if( $xmlname == $searchname )
				{
					$this->seriesid = trim( $ids[ $x ] );
				}
			}
		}
	}
	
	private function get_series_data()
	{				
		$temp_file = dirname( __FILE__ ) . "/tvdb/" . $this->seriesid;
		$temp_file = str_replace( '\\', '/', $temp_file ); 

		if( file_exists( $temp_file ) )
		{
			if( filemtime( $temp_file ) + 3600 > time() )
			{
				$this->xml = file_get_contents( "zip://" . $temp_file . "#en.xml" );
				return;
			}
			unlink( $temp_file ); 
		}

		$zip = file_get_contents( 'http://thetvdb.com/api/' . TVDB_API_KEY . '/series/' . $this->seriesid . '/all/en.zip' );
				
		if( $zip === false )
			return;
		
		if( !file_exists( "tvdb/" ) )
			mkdir( "tvdb" );
		
		if( file_put_contents( $temp_file, $zip ) === false )
			return;
		
		$this->xml = file_get_contents( "zip://" . $temp_file . "#en.xml" );
	}
}