<?php

define( 'SERIES_DIR' , 'Z:\\Series' );
define( 'TVDB_API_KEY' , '1B15C109D6C05699' );

include( "utils.php" );
include( "qbittorrent.php" );
include( "tvdbworker.php" );
include( "eztvworker.php" );
include( "mediaworker.php" );
include( "series.php" );
include( "season.php" );
include( "episode.php" );
include( "eztv.php" );
include( "tvdb.php" );
include( "mediainfo.php" );


class Cleaner
{
	private $eztv = null;
	private $tvdb = null;
	private $media = null;
	private $missing = false;
	private $attempt = false;
	private $useFilebot = false;

	
	public function __construct( $path )
	{
		$cleaned = false;

		$dirs = glob( $path . '\\*', GLOB_ONLYDIR );	
		
		print "Scanning " . $path . "...\n";
		print "Detected " . count( $dirs ) . " series...\n";
		
		$this->get_eztv_listing( $path, $dirs );		
		$this->get_tvdb_listing( $path, $dirs );
		
		
		while( $cleaned == false )
		{		
			$this->useFilebot = Utils::ask_yes( "Use File bot?" );

			if( $this->tvdb )
			{
				$this->missing = Utils::ask_yes( "Find missing files?" );

				if( $this->missing != false && $this->eztv )
				{
					$this->attempt = Utils::ask_yes( "Attempt torrent download for missing?" );
				}
			}	

			$this->media = Utils::ask_yes( "Check for corrupt files?" ) ? new MediaInfo() : null;
		
			foreach ( $dirs as $directory ) 
			{			
				$current_series = substr( $directory , strlen( $path ) + 1 );		
				
				printf( "Processing : %-100s \n", $current_series );	
		
				$this->clean_series_top_level_directories( $directory, $current_series );
				$this->clean_series_top_level_files( $directory, $current_series );
				$this->clean_series_season_directories( $directory, $current_series );
				$this->clean_series_season_files( $directory, $current_series );
			}

			$cleaned = !Utils::ask_yes( "Run again?" );
		}
	}

	private function get_tvdb_listing( $path, $dirs )
	{
		if( Utils::ask_yes( "Get TVDB Listing?" ) )
		{
			$this->tvdb = new Tvdb();
			$this->tvdb->downloads_series_data( $path, $dirs );
			$this->tvdb->free_workers();
		}
	}
	
	private function get_eztv_listing( $path, $dirs )
	{
		if( Utils::ask_yes( "Get EZTV Torrents?" ) )
		{
			$this->eztv = new Eztv( array() );
			$this->eztv->downloads_series_data( $dirs );
			$this->eztv->free_workers();
		}
	}

	private function clean_series_season_files_filebot( $directory, $current_series )
	{
		if( $this->useFilebot == false )
			return;
		
		$fbdirectory = str_replace( "\\", "/", $directory );
		$filebotlines = explode( "\n" , shell_exec( "filebot -r --db TheTVDB --action test -rename \"$fbdirectory/\" -non-strict 2> nul" ) );
		$dirty = false;
			
		foreach( $filebotlines as $line )
		{
			if( preg_match( "/^\[TEST\] Rename (\[(.*?)\]).*?(\[(.*)\])/i" , $line , $matches ) == 1 )
			{			
				$old = explode( "\\" , $matches[2] );
				$old = array_pop( $old );
				$new = $matches[4];
				printf( "\tConsider renaming : %-50s => %-50s \n" , $old , $new );
				$dirty = true;
			}
		}
		
		if( $dirty == false )
			return;
		
		if( Utils::ask_yes( "\tRename files?" ) == false ) 
		{
			return;
		}
			
		shell_exec( "filebot -r --db TheTVDB --action move -rename \"$fbdirectory/\" -non-strict 2> nul" );
		printf( "\tCleaned \n" );
	}

	private function clean_series_season_duplicates( $directory, $current_series )
	{
		$files = array_filter( glob( $directory . "\\*" ), 'is_file' );
		
		$names = array();
		
		foreach( $files as $file ) 
		{
			$pathinfo = array_filter( explode( '\\', $file ) );
			$result = array_pop( $pathinfo );
			$names[] = substr( $result , 0 , strripos( $result, "." ) );
		}
		
		$dups = array();

		foreach( array_count_values( $names ) as $val => $c )
		{
			if($c > 1) printf( "\t%-20s: %-100s \n", "Duplicate" , $val );
		}
	}

	private function clean_series_season_files( $path, $current_series )
	{		
		$directories = glob( $path . "\\*" , GLOB_ONLYDIR );
		
		$replace = array( 
			'(' => '\(', 
			')' => '\)',
			'!' => '\!' 
		); 				
		
		$clean_series = Utils::str_replace_assoc( $replace, $current_series );
		
		foreach ( $directories as $directory ) 
		{		
			$this->clean_series_season_files_filebot( $directory, $current_series );
					
			$files = array_filter( glob( $directory . "\\*" ), 'is_file' );
				
			foreach( $files as $file ) 
			{
				$path_parts = pathinfo( $file );
				$matches = null;
				$season_num = null;
				
				if( $path_parts['basename'] == "Thumbs.db" )
				{
					unlink( $file );
					printf( "\t%-20s: %-100s \n", "Deleted" , $file );
				}				

				$pathinfo = array_filter( explode( '\\', $file ) );
				$result = array_pop( $pathinfo );
				$season_num = array_pop( $pathinfo );
				$season_num = trim( substr( $season_num , 7 ) );

				if( $this->media )
					$this->media->add_file_to_check( $file );
												
				if( preg_match( "/^$clean_series\.? - ($season_num)x([0-9]+|Special\s+?[0-9]+)/i" , $path_parts['basename'] ) == 0 &&
					preg_match( "/^$clean_series\.? - (\[(.*?)\]).*?(\[(.*)\])/i" , $path_parts['basename'] ) == 0
				)
				{		
					printf( "\t%-20s: %-100s \n", "Season" , $season_num );
					printf( "\t%-20s: %-100s \n", "File" , $path_parts['basename'] );
					
					if( preg_match( "/^$clean_series.*/i" , $path_parts['basename'] ) == 0 )
					{	
						printf( "\t%-20s: %-100s \n", "Does not start with" , $clean_series );
					}
					else if( preg_match( "/.*?($season_num)x([0-9]+|Special\s+?[0-9]+)/i" , $path_parts['basename'] ) == 0 )
					{	
						$matches = array();
						preg_match( "/.*?([0-9]+)x([0-9]+|Special\s+?[0-9]+)/i" , $path_parts['basename'] , $matches );
						if( count( $matches) > 0 )
						{
							printf( "\t%-20s: %-100s \n", "Incorrect Season", $matches[1] );
							
							$newdirectory = substr( $directory , 0 , strlen( "Season $season_num" ) * -1 ) . "Season $matches[1]";
							printf( "\tMove : %-100s - %-100s \n", $file , $newdirectory . "\\" . $path_parts['basename'] );
							
							if( Utils::ask_yes( "\tMove files to season \"$matches[1]\"?" ) == true )
							{
								if( !file_exists( $newdirectory ) )
								{
									print "\tMake : " . $newdirectory. " \n";
									mkdir( $newdirectory );
								}
								
								rename( $file , $newdirectory . "\\" . $path_parts['basename'] );	
							}
						}
						else
						{
							printf( "\t%-20s: %-100s \n", "Incorrect Season", "????" );
						}
					}
					else
					{
						printf( "\t%-20s: %-100s \n", "Dirty", $path_parts['basename'] );
					}
					
					printf( "\n" );
				}					
			}
			

			if( $this->media )
				$this->media->parse_season_files_metadata();
						
			$this->clean_series_season_files_find_missing( $directory, $current_series, $files );
			$this->clean_series_season_duplicates( $directory, $current_series );
		}
	}

	private function clean_series_season_files_find_missing( $directory , $current_series, $files )
	{
		if( $this->missing == false )
			return;

		$c_pathinfo = array_filter( explode( '\\', $directory ) );
		$c_season_num = array_pop( $c_pathinfo );
		$c_season_num = trim( substr( $c_season_num , 7 ) );

		$series = $this->tvdb->get_series();

		if( !isset( $series[ $current_series ] ) )
		{
			return;
		}

		$series = $series[ $current_series ];
		$seasons = $series->get_seasons();
						
		if( !isset( $seasons[ $c_season_num ] ) )
		{
			return;
		}

		$episodes = $seasons[ $c_season_num ]->get_episodes();

		foreach( $episodes as $episode ) 
		{
			$epnum = $c_season_num . "x" . sprintf( '%02d' , $episode->get_episode_num() );
			$found = false;
			
			foreach( $files as $file ) 
			{
				if( preg_match( "/$epnum/", $file ) )
				{
					$found = true;
					break;
				}
			}
			
			if( $found == false )
			{
				printf( "\tMissing %s - Season %-3s - Episode %-3s\n", $current_series , $c_season_num, $episode->get_episode_num() );
				
				if( $this->eztv )
				{
					if( $this->attempt == false )
						continue;

					$this->eztv->download( $current_series , $c_season_num , $episode->get_episode_num() );
				}
			}
		}
	}	

	private function clean_series_season_directories( $directory, $current_series )
	{	
		$directories = glob( $directory . "\\*" , GLOB_ONLYDIR );
		
		foreach ( $directories as $subdirectory ) 
		{		
			$subdirectories = glob( $subdirectory . "\\*" , GLOB_ONLYDIR );
			
			foreach ( $subdirectories as $subdirectoryy ) 
			{
				printf( "\tSub directory of season found: : %-100s \n", $subdirectoryy );
				
				if( rmdir( $subdirectoryy ) )
				{
					printf( "\tDeleted empty directory : %-100s \n", $subdirectoryy );
				}
			}
		}
	}

	private function clean_series_top_level_files( $directory, $current_series )
	{	
		$files = array_filter( glob( $directory . "\\*" ), 'is_file' );
		
		if( count( $files ) > 0 )
		{
			printf( "\tFound file at top level, iterating \n", $current_series );
			
			foreach( $files as $file ) 
			{
				$path_parts = pathinfo( $file );
				$matches = null;
				$season_num = null;
				
				if( $path_parts['basename'] == "Thumbs.db" )
				{
					unlink( $file );
					printf( "\tDeleted : %-100s \n", $file );
				}			

				$lower_current_series = preg_quote( $current_series );
				
				if( preg_match( "/^$lower_current_series\.? - (\d+)x[0-9]+/" , $path_parts['basename'] , $matches ) == 1 )
				{
					$season_num = intval( $matches[1] );
					
					if( !file_exists( $path_parts['dirname'] . "\\Season $season_num" ) )
					{
						print "\tMake : " . $path_parts['dirname'] . "\\Season $season_num \n";
						mkdir( $path_parts['dirname'] . "\\Season $season_num" );
					}
					
					if( file_exists( $path_parts['dirname'] . "\\Season $season_num\\" . $path_parts['basename'] ) )
					{
						print "\tFile exists: " . $path_parts['dirname'] . "\\Season $season_num\\" . $path_parts['basename'] . "\n";
					}	

					printf( "\tMove : %-100s - %-100s \n", $file , $path_parts['dirname'] . "\\Season $season_num\\" . $path_parts['basename'] );

					if( Utils::ask_yes( "\tMove file?" ) == true )
					{
						rename( $file , $path_parts['dirname'] . "\\Season $season_num\\" . $path_parts['basename'] );		
					}							
				}
				else
				{
					printf( "\tCan't identify season number : %-100s \n", $file );
				}
			}
		}
	}
		
	private function clean_series_top_level_directories( $directory, $current_series )
	{
		$dirty = false;
		$series = $this->tvdb->get_series();
		$directories = glob( $directory . "\\*" , GLOB_ONLYDIR );
		
		foreach ( $directories as $dir ) 
		{		
			$pathinfo = array_filter( explode( '\\', $dir ) );
			$result = array_pop( $pathinfo );
			
			if( preg_match( "/^Season ([0-9]+)$/" , $result ) == 0 )
			{
				$dirty = true;
				printf( "\tDirty directory name : %-25s - %-100s \n", "/^Season [0-9]+$/", $dir );
				
				$count_childs = count( glob( $dir . "\\*" ) );
				
				if( $count_childs == 0 )
				{
					rmdir( $dir );
					printf( "\tDeleted empty directory : %-100s \n", $dir );
				}
			}
		}
		
		if( isset( $series[ $current_series ] ) )
		{			
			$seasons = $series[ $current_series ]->get_seasons();
			
			foreach( $seasons as $season )
			{
				$check = $directory . "\\Season " . $season->get_season_num();
				if( !file_exists( $check ) )
				{
					printf( "\tMissing season : %-3s in %-100s\n", $season->get_season_num() , $current_series);
				}
			}
		}
	}	
}


new Cleaner( SERIES_DIR );
