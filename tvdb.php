<?php

class Tvdb
{
	private $workers = null;
	private $series = null;

	public function __construct() 
	{
		$this->series = array();
		$this->workers = array();	
	}

	public function __destruct()
	{
	  	$this->series = null;
	}

	public function downloads_series_data( $path, $dirs )
	{
		print "Fetching TVDB metadata...\n";

		$current_date = new DateTime();
		$width = 50;		
				
		foreach ( $dirs as $directory ) 
		{		
			print ".";
			$width++;

			if( $width % 50 == 0) print ( $width - 50 ) . "\n";	

			$this->wait_limit_workers( 3 );
					
			$current_series = substr( $directory , strlen( $path ) + 1 );		
			$this->workers[ $current_series ] = new TvdbWorker( $current_series );		
			$this->workers[ $current_series ]->start();	
		}	
		
		print ($width - 50) . "\n";
		
		$this->wait_for_workers();	
		$this->parse_series_data();	
		
		print "Done.\n";		
	}

	public function parse_series_data()
	{
		print "Parsing TVDB metadata...\n";

		$current_date = new DateTime();
		$width = 50;	
		$notdone = 1;

		foreach( $this->workers as $worker )
		{	
			if( $worker->is_processed() == false )
			{
				$xml = $worker->get_xml();
				$nseries = new Series( $worker->get_series() );
				
				$this->series[ $worker->get_series() ] = $nseries;
			
				if( $xml !== null )
				{
					$tree = new SimpleXMLElement( $xml );
					
					$episodes = $tree->xpath( './/Episode' );
											
					foreach( $episodes as $episode )
					{
						$epname = trim( $episode->xpath( './/EpisodeName' )[0] ); 								
						$epseasonnum = trim( $episode->xpath( './/SeasonNumber' )[0] );		
						$epaireddate = trim( $episode->xpath( './/FirstAired' )[0] );		
						$epepisodenum = trim( $episode->xpath( './/EpisodeNumber' )[0] );	
											
						$airdate = $epaireddate == "" ? new DateTime( "2050-01-01" ) : new DateTime( $epaireddate );
						
						if( $airdate <= $current_date && $epseasonnum > 0 )
						{
							$season = $nseries->get_season( $epseasonnum );	
							$newepisode = new Episode( array( $epepisodenum , $epname ) );										
							$season->add_episode( $newepisode );				
						}
					}	
				}
			}
		}		
	}

	private function wait_for_workers()
	{
		sleep( 1 );

		print "Waiting for threads";

		$count = 2;

		while( $count > 1 )
		{
			$count = 0;

			foreach( $this->workers as $worker )
			{
				if( $worker->is_working() == true )
				{
					$count++;
				}
			}
			usleep( 50000 );
			print ".";
		}

		usleep( 50000 );

		print "\n";
	}

	private function wait_limit_workers( $num )
	{
		$count = $num + 1;

		while( $count > $num )
		{
			$count = 0;

			foreach( $this->workers as $worker )
			{
				if( $worker->is_working() == true )
				{
					$count++;
				}
			}
			usleep( 20000 );
		}

		usleep( 20000 );
	}

	public function get_series()
	{
		return $this->series;
	}

	public function free_workers()
	{
		for( $x =0; $x < count( $this->workers ); $x++ )
		{
			unset( $this->workers[ $x ] );
		}

		gc_collect_cycles();
	}
}