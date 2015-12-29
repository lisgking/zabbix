<?php

class IDSDaemonControl
	{

	/*
     * These constants must match the enum in idsd.h 
     */

	const IDSD_ARGLIST =  1;
    const IDSD_BEGIN   =  2;
    const IDSD_DATA    =  3;
    const IDSD_EOT     =  4;
    const IDSD_EDIT    =  5;
    const IDSD_ELEMENT =  6;
    const IDSD_END     =  7;
    const IDSD_ENVLIST =  8;
    const IDSD_EXECUTE =  9;
    const IDSD_FAILURE = 10;
    const IDSD_GETFILE = 11;
    const IDSD_ID      = 12;
    const IDSD_NOOP    = 13;
    const IDSD_PING    = 14;
    const IDSD_PUTFILE = 15;
    const IDSD_QUIT    = 16;
    const IDSD_STRING  = 17;
    const IDSD_SUCCESS = 18;

	protected $host;
	protected $port;
	protected $socket;
	protected $isConnected = false;

	function __construct ( ) 
		{
		}

	function __destruct ( ) 
		{
		//$this->disconnect ( );
		}

	private function authenticate ( $user 
                                  , $password )
		{
		$this->putString ( $user );
        $this->putString ( $password );
		
		if ( $this->getStatus ( ) != self::IDSD_SUCCESS )
			{
			error_log ( "Authentication failed for user \""
                      . $user . "\"" );

			return false;
			}

		return true;
		}

	public function connect ( $host, $port, $user, $password ) 
		{
        $errno = 0;

		$this->socket = fsockopen ( "tls://" . $host, $port, $errno );
		if ( $this->socket === false )
			{
			throw new Exception ( "fsockopen() failed: errno " . $errno );
            }

		$this->host = $host;
		$this->port = $port;

		$this->isConnected = true;

		return $this->authenticate ( $user, $password );
		}

	public function disconnect ( )
		{
		if ( $this->isConnected == true )
			{
			$this->putCommand ( self::IDSD_QUIT );
			fclose ( $this->socket );
			$this->isConnected = false;
			}
		}

	public function startServer ( $arguments 
                                , $environment 
                                , $startupDirectory )
		{
		$file = 'oninit';
		$this->putCommand ( self::IDSD_EXECUTE );
		$this->putString ( $file );

		/*
         * Send arguments. 
         */

		$this->putCommand ( self::IDSD_ARGLIST );

		foreach ( $arguments as $argument )
			{
			$this->putCommand ( self::IDSD_ELEMENT );
			$this->putString ( $argument );
			}

		$this->putCommand ( self::IDSD_END );

		/*
         * Send the environment
         */

		$this->putCommand ( self::IDSD_ENVLIST );

		foreach ( $environment as $variable )
			{
			$this->putCommand ( self::IDSD_ELEMENT );
			$this->putString ( $variable );
			}

		$this->putCommand ( self::IDSD_END );

		/*
         * Tell IDSD which directory to run oninit from.
         */

		$this->putString ( $startupDirectory );

		/*
         * Send a boolean indicating we will wait for the child
         */

		$this->putBoolean ( true );

		$status = $this->getStatus ( );
		$return = $this->getUInt32 ( );
		//error_log ( "startServer status: " . $status . ", return " . $return );
		return ( $return == 0 );
		}

	public function stopServer ( $arguments
                               , $environment )
		{
		}

	protected function getStatus ( )
		{
		return $this->getUInt16 ( );
		}

	protected function getString ( )
		{
		$length = $this->getUInt16 ( );
		return $this->get ( $length );
		}

	protected function getUInt16 ( )
		{
		return $this->ntohs ( $this->get ( 2 ) ); 
		}

	protected function getUInt32 ( )
		{
		return $this->ntohl ( $this->get ( 4 ) ); 
		}

	protected function get ( $size )
		{
		$data = "";

		if ( ( $data = fread ( $this->socket, $size ) ) === false )
			{
			throw new Exception ( "fread() failed" );
			}


/*
		if ( ( $data = socket_read ( $this->socket, $size ) ) === false )
			{
			throw new Exception ( "socket_read() failed: "
                                . socket_strerror ( socket_last_error ( ) ) );
			}
*/

		return $data;
		}

	protected function putBoolean ( $value )
		{
		$tmp = ( $value == false ) ? 0 : 1;

		return $this->putUInt16 ( $tmp );
		}

	protected function putCommand ( $command )
		{
		return $this->putUInt16 ( $command );
		}

	public function putFile ( $source
                            , $destination ) 
		{
		$handle = null;
		if ( ( $handle = fopen ( $source, "r" ) ) === FALSE )
			{
			throw new Exception ( "Error opening file [" . $source . "]" );
			}

		//$fp = fopen ( $source, "r" );

		//error_log ( "handle = [" . $handle . "]" );
		$stat = fstat ( $handle );
		$size = $stat [ "size" ];
		//error_log ( "size = " . $size );
		$data = fread ( $handle, $size );
		
		$this->putCommand ( self::IDSD_PUTFILE );
		$this->putString ( $destination );
		$this->putUInt32 ( $size );
		$this->putUInt16 ( 0644 );
		$this->putBoolean ( true );
		$this->put ( $data, $size );

		return ( $this->getStatus ( ) == self::IDSD_SUCCESS );
		}

	protected function putString ( $value )
		{
		$length = strlen ( $value );

		if ( ! $this->putUInt16 ( $length ) )
			return false;

		if ( ! $this->put ( $value, $length ) )
			return false;

		return true;
		}

	protected function putUInt16 ( $value )
		{
		return $this->put ( $this->htons ( $value ), 2 );
		}

	protected function putUInt32 ( $value )
		{
		return $this->put ( $this->htonl ( $value ), 4 );
		}

	protected function put ( $data, $size )
		{
		if ( fwrite ( $this->socket, $data, $size ) === false )
			{
			throw new Exception ( "fwrite() failed" );
			}
			
/*
		if ( socket_write ( $this->socket, $data, $size ) === false ) 
			{
			throw new Exception ( "socket_write() failed: " 
                                . socket_strerror ( socket_last_error ( ) ) );
			}
*/

		return true;
		}

	protected function htonl ( $value )
		{
		$tmp = unpack ( "L", pack ( "I", $value ) );
		return pack ( "N", $tmp [ 1 ] );
		}

	protected function htons ( $value )
		{
        $tmp = unpack ( "S", pack ( "S", $value ) );
		return pack ( "n", $tmp [ 1 ] );
		}

	protected function ntohl ( $value )
		{
		$tmp = unpack ( "N", $value );
		$tmp = unpack ( "I", pack ( "I", $tmp [ 1 ] ) );
		return $tmp [ 1 ];
		}

	protected function ntohs ( $value )
		{
		$tmp = unpack ( "n", $value );
		$tmp = unpack ( "S", pack ( "S", $tmp [ 1 ] ) );
		return $tmp [ 1 ];
		}
	}
?>
