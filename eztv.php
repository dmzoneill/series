<?php

class Eztv 
{
	private $serieslist = null;
	protected $settings = null;
	private $workers = null;
	
	public function __construct( $settings ) 
	{
		$this->workers = array();
		$this->serieslist = array();

		$this->settings = array_merge( array(
			'quality' => 'low',
			'allow_repacks' => true
		), $settings);
	}

	public function __destruct()
	{
	  	$this->workers = null;
	  	$this->serieslist = null;
	}

	private function parse_episodes_from_workers()
	{
		print "Parsing EZTV metadata...\n";

		foreach( $this->workers as $worker )
		{
			if( $worker->is_working() == false && $worker->is_processed() == false )
			{
				preg_match_all( '/<tr.*?>(.*?)<\/tr>/si', $worker->get_html() , $trs, PREG_SET_ORDER );

				foreach( $trs as $tr ) 
				{
					preg_match( "/class=\"epinfo\">(.*?)<\/a>/si", $tr[0] , $epname );
					preg_match( "/\"(magnet:.*?)\"/si", $tr[0] , $epmagnet );

					if( count( $epmagnet ) > 1 && count( $epname ) > 1 )
					{
						$this->serieslist[ $worker->get_series_name() ][] = array( $epname[1], $epmagnet[1] );
					}					
				}
			}
		}
	}

	public function downloads_series_data( $series ) 
	{
		print "Fetching EZTV metadata...\n";

		$series = Utils::get_base_name_dirs( $series );
		$data = file_get_contents( "https://eztv.ag" );
		$width = 50;	

		preg_match( "/<select.*?<\/select>/is", $data, $match );
		preg_match_all( '/<option value="(.*?)"\s*>(.*?)<\/option>/', $match[0], $matches, PREG_SET_ORDER );

		$workers = array();
		$workingCount = 0;
		
		foreach( $matches as $val ) 
		{			
			if( !in_array( strtolower( $val[2] ), $series ) ) continue;	

			print ".";
			$width++;
			
			if( $width % 50 == 0) print ($width - 50) . "\n";
					
			$this->wait_limit_workers( 3 );

			$thread = new EztvWorker( array( $val[2] , $val[1] ) );	 					
			$this->workers[] = $thread;
			$thread->start();
		}

		print ($width - 50) . "\n";

		$this->wait_for_workers();	
		$this->parse_episodes_from_workers();
		print "Done.\n";
		$this->print_unfound_series( $series );		
	}

	public function download( $current_series , $season_num , $episode_num )
	{
		$lower = strtolower( $current_series );
		$attempted = 0;

		if( isset( $this->serieslist[ $lower ] ) )
		{
			$series = $this->serieslist[ $lower ];

			foreach( $series as $downloads )
			{
				$epname = $downloads[0];
				$epmagnet = $downloads[1];

				$episode_num = sprintf( '%02d' , $episode_num );
				$season_num = sprintf( '%02d' , $season_num );

				$regex1 = "/.*s" . $season_num . "e" . $episode_num . ".*/i";
				$regex2 = "/.*" . $season_num . "x" . $episode_num . ".*/i";	

				if( preg_match( $regex1 , $epname ) == 1 || preg_match( $regex2 , $epname ) == 1 )
				{
					printf( "\tDownload: $epname \n" );
					$attempted = 0;
					break;
				}
				else
				{
					$attempted++;
				}
			}
		}

		if( $attempted > 0 )
		{
			printf( "\tSearch %s torrents, Failed to find torrent for %s %s %s\n" , $attempted, $current_series, $season_num, $episode_num );;
		}
	}

	private function print_unfound_series( $series )
	{
		if( Utils::ask_yes( "Show eztv missing series?" ) == false ) 
		{
			return;
		}

		$myseries = Utils::get_base_name_dirs( $series );
		$ezseries = array_map( 'strtolower', array_keys( $this->serieslist ) );

		foreach( $myseries as $value )
		{
			if( !in_array( $value , $ezseries ) )
			{
				printf( "\tEztv missing: $value \n" );
			}
		}
	}

	private function wait_for_workers()
	{
		sleep( 1 );

		print "Waiting for threads";

		$count = 1;

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

	public function free_workers()
	{
		for( $x =0; $x < count( $this->workers); $x++ )
		{
			unset( $this->workers[ $x ] );
		}

		gc_collect_cycles();
	}
}