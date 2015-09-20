<?php

class Utils
{
	public static function ask_yes( $action )
	{
		print "$action [y/N]:";
		$handle = fopen( "php://stdin" , "r" );
		$line = fgets( $handle );
		
		if( strtolower( trim( $line ) ) == 'y' )
			return true;
		
		return false;
	}

	public static function str_replace_assoc(array $replace, $subject) 
	{ 
	   return str_replace( array_keys( $replace ), array_values( $replace ), $subject );    
	} 

	public static function get_base_name_dirs( $dirs )
	{
		$rdirs = array();

		foreach( $dirs as $dir )
		{
			$parts = explode( "\\" , $dir );
			$rdirs[] = strtolower( trim( array_pop( $parts ) ) );
		}

		return $rdirs;
	}
}