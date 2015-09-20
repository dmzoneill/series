<?php

class MediaWorker extends Thread
{
	private $working = false;
	private $file = null;
	private $result = null;
	private $processed = false;	
	
	public function __construct( $file )
	{
		$this->file = $file;
	}

	public function __destruct()
	{
		$this->file = null;
		$this->result = null;
		$this->processed = null;	
	}
	
	public function run()
	{		
		$this->working = true;
		$this->get_media_info();		
		$this->working = false;	
	}
	
	public function is_working()
	{
		return $this->working;
	}
	
	public function get_result()
	{
		$this->processed = true;
		return $this->result;
	}

	public function get_file_name()
	{
		return $this->file;
	}

	public function is_processed()
	{
		return $this->processed;
	}
	
	private function get_media_info()
	{		
		$cmd = "mediainfo \"" . $this->file . "\"";
		$this->result = shell_exec( $cmd );
	}
}