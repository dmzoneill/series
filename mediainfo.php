<?php

class MediaInfo
{
	private $unchecked = null;
	private $corrupted = null;
	private $mediaWorkers = null;
	private $unchecked_locked = false;

	public function __construct() 
	{
		$this->unchecked = array();
		$this->corrupted = array();
		$this->mediaWorkers = array();	
	}

	public function __destruct()
	{
		$this->unchecked = null;
		$this->corrupted = null;
		$this->mediaWorkers = null;
	}

	public function add_file_to_check( $file )
	{
		$this->unchecked[] = $file;
	}

	public function parse_season_files_metadata()
	{
		while( ( $index = array_shift( $this->unchecked ) ) !== null )
		{		
			$this->wait_limit_workers( 2 );
						
			$this->mediaWorkers[ $index ] = new MediaWorker( $index );		
			$this->mediaWorkers[ $index ]->start();	
		}	
		
		$this->wait_for_workers();	
		$this->check_season_files_metadata();			
	}

	private function check_season_files_metadata()
	{
		foreach( $this->mediaWorkers as $worker )
		{	
			if( $worker->is_processed() == false )
			{
				$result = $worker->get_result();

				if( $result !== null )
				{
					$lines = explode( "\n" , $result );
					$info = array();

					foreach( $lines as $line ) 
					{
						if( strstr( $line , ":" ) )
						{
							$parts = explode( ':', $line, 2 );
							$info[ trim( $parts[0] ) ] = trim( $parts[1] );
						}
						else
						{
							$info[ trim( $line ) ] = "";
						}
					}

					if( !isset( $info[ "Duration" ] ) )
					{

						printf( "\tCorrupt file: %s\n" , $worker->get_file_name() );
					}		
				}
			}
		}		
	}

	private function wait_for_workers()
	{
		sleep( 1 );

		$count = 2;

		while( $count > 1 )
		{
			$count = 0;

			foreach( $this->mediaWorkers as $worker )
			{
				if( $worker->is_working() == true )
				{
					$count++;
				}
			}
			usleep( 1000 );
		}

		usleep( 1000 );
	}

	private function wait_limit_workers( $num )
	{
		$count = $num + 1;

		while( $count > $num )
		{
			$count = 0;

			foreach( $this->mediaWorkers as $worker )
			{
				if( $worker->is_working() == true )
				{
					$count++;
				}
			}
			usleep( 1000 );
		}

		usleep( 1000 );
	}

	public function free_workers()
	{
		for( $x =0; $x < count( $this->mediaWorkers ); $x++ )
		{
			unset( $this->mediaWorkers[ $x ] );
		}

		gc_collect_cycles();
	}
}