<?php
	/*
	 *  Software: SpeedyMail SMTP Class
	 *   Version: 1.0.1
	 *      Site: http://codesector.net/projects/SpeedyMail
	 *   Authors: Thomas Krause
	 * Copyright: (c) 2011 - 2012 Thomas Krause. All Rights Reserved.
	 *   License: SpeedyMail is distributed under the GPL v2 and MIT licenses.
	 * 			 This software is provided without any kind of warrenty use at your
	 *			 own risk!
	 * 
	 * SpeedyMail is an smtp class written in PHP which aims to provide
	 * developers with a straightforward no bloat mailing class which is
	 * compliant will all RFC SMTP Standards. More information can be found
	 * at the site provided above.
	 * 
	 */


	/**
	 * SpeedyMail 
	 *
	 * Allows developers to quickly and easily send an email using method Chaining
	 * and is standards compliant
	 * 
	 * @author Thomas Krause
	 * @copyright 2012 Thomas Krause
	 * @license http://codesector.net/licenses/mit.txt MIT License
	 * @version Release: 1.0.1
	 * @link http://codesector.net/projects/SpeedyMail
	 */

	class SpeedyMail {
		// Private properties
		private $_version = '1.0.1';

		// Protected properties
		protected $_headers = array();
		protected $_recipients = array();
		protected $_attachments = array();
		protected $_replacements = array();

		protected $_crlf = "\r\n";
		protected $_wordwrap = 0;
		protected $_charset = 'iso-8859-1';
		protected $_content_type = 'text/plain';
		protected $_encoding = '8bit';
		protected $_boundary = NULL;

		protected $_subject = NULL;
		protected $_body = NULL;

		// Public methods
		public static function init() { return new self(); }
		public function __construct() {
			if ( isset( $_SERVER['SERVER_NAME'] ) && !empty( $_SERVER['SERVER_NAME'] )) { // Do we know the name of the server we're running on?
				$this->setFrom( 'no-reply@'.$_SERVER['SERVER_NAME'] ); // Set the address to no-reply at the server name
			} else {
				$this->setFrom( 'From', 'root@localhost.localdomain' ); // Apparently, no one helped us out...fall back
			}
			
			$this->_boundary = ( md5( time() )); // Generate a somewhat random string to designate our barrier
			$this->setHeader( 'MIME-Version', '1.0' ); // Set the MIME Version to use
			$this->setHeader( 'X-Mailer', 'SpeedyMail/'.$this->_version ); // Set the X-Mailer header...Can be overridded or removed VIA the unsetHeader method
			
			$this->setContentType(); // Just use the defaults of the function
		}

		public function setReplyTo( $reply ) { $this->setHeader( 'Reply-To', $reply ); }
		public function setSubject( $subject ) { $this->_subject = $subject; return $this; }
		public function setWordWrap( $wrap = 0 ) { $this->_wordwrap = $wrap; return $this; }
		public function setCharset( $charset = 'iso-8859-1' ) { $this->_charset = $charset; return $this; }
		public function setTransferEncoding( $encoding = '8bit' ) { $this->_encoding = $encoding; return $this; }
		public function setDeliveryReceipt( $addr ) { $this->setHeader( 'Disposition-Notification-To', $addr ); }
		public function setFrom( $from ) { $this->setHeader( 'From', $from ); $this->setHeader( 'Return-Path', $from ); return $this; }
		public function setContentType( $content_type = 'text/plain', $force = FALSE ) {
			$this->_content_type = $content_type;
			
			if ( empty( $this->_attachments ) || $force ) {
				$this->setHeader( 'Content-Type', sprintf( '%s; charset="%s"', $content_type, $this->_charset ));
			}
			
			return $this;
		}
		
		/**
		 * setPriority, sets the urgency in which the SMTP servers involved should handle the message
		 * 
		 * @param	String/Integer	$priority	Contains the priority level to be the new level of this message
		 * @return	None						Does not return a value
		 */
		public function setPriority ( $priority = 'normal' ) {
			switch ( strtolower( (string)$priority )) {
				case 'high':
				case '1':
					$this->setHeader('X-Priority', '1');
					$this->setHeader('X-MSMail-Priority', 'High');
					$this->setHeader('Importance', 'High');
					break;
				case 'normal':
	            case '3':
	                $this->setHeader('X-Priority', '3');
					$this->setHeader('X-MSMail-Priority', 'Normal');
					$this->setHeader('Importance', 'Normal');
					break;
	            case 'low':
	            case '5':
	                $this->setHeader('X-Priority', '5');
					$this->setHeader('X-MSMail-Priority', 'Low');
					$this->setHeader('Importance', 'Low');
					break;
			}
		}
		
		/**
		 * setBody, sets the message body of the email to be sent.
		 * This function can take a URL, File Path, or Standard message text.
		 * If a URL or File Path is entered, the body will be set to the contents of this File or URL.
		 * 
		 * @param	String	$body			Can contain raw text, html, a URL, or FilePath accessible by this server
		 * @param	String	$type			Currently supports types of "text" and "html". This is the Content-Type for the message body
		 * @param	String	$template_type	If body is anything other than raw input, this should be specified to set the method for retrieving the content.
		 * 									Supports 'text' or 'html, 'php', 'url'
		 * @param	Array	$context		If $template_type is set to URL this param should be set according to PHP Doc http://php.net/manual/en/function.stream-context-create.php
		 */
		public function setBody( $body, $type = 'text', $template_type = '', $context = NULL ) {
			switch( strtolower( $type )) {
				case '':
				case 'text':
				case 'plain':
					if ( $this->_wordwrap ) {
						$body = wordwrap( $body, $this->_wordwrap );
					}
					$type = 'text/plain';
					break;
				case 'html':
					$type = 'text/html';
					break;
			}
			
			if ( !empty( $body ) && file_exists( $body )) {
				switch( strtolower( $template_type )) {
					case '':
					case 'text':
					case 'html':
						$body = file_get_contents( $body, FALSE );
						break;
					case 'php':
						ob_start();
							include $body;
							$body = ob_get_contents();
						ob_end_clean();
						break;
					case 'url':
						if ( $context ) {
							$body = file_get_contents( $body, FALSE, stream_context_create( $context ));
						} else {
							$body = file_get_contents( $body, FALSE );
						}
						break;
				}
			}
			
			$this->setContentType( $type );
			$this->_body = $body;
			
			return $this;
		}
		
		/**
		 * unsetHeader/setHeader, sets and unsets a mail header. Note the name/key is case sensative.
		 */
		public function unsetHeader( $name ) { unset( $this->_headers[$name] ); return $this; }
		public function setHeader( $key, $value ) {
			if ( empty( $key ))
				return;
			
			$this->_headers[$key] = $value;
			return $this;
		}
		
		public function bind( $key, $value ) { $this->_replacements[$key] = $value; return $this; }
		public function addTo( $addr, $name ) { $this->addRecipient( $addr, $name, 'to' ); return $this; }
		public function addCc( $addr, $name ) { $this->addRecipient( $addr, $name, 'cc' ); return $this; }
		public function addBcc( $addr, $name ) { $this->addRecipient( $addr, $name, 'bcc' ); return $this; }
		public function addAttachment( $attachment ) {
			if ( empty( $this->_attachments )) {
				$this->setHeader( 'Content-Type', sprintf( 'multipart/mixed; boundary=%s', $this->_boundary ));
			}
			
			$this->_attachments[] = $attachment;
			return $this;
		}
		public function addRecipient( $addr, $name, $type = 'to' ) {
			if ( empty( $addr ))
				return;
			
			$addr = empty( $name ) ? $addr : sprintf( '%s <%s>', $name, $addr ); // Set the address up correctly
			
			switch( strtolower( $type )) {
				case 'to':
				case 'cc':
				case 'bcc':
					$this->_recipients[$addr] = $type;
					break;
			}
			
			return $this;
		}
		
		public function send() {			
			$to = implode( ',', $this->getRecipients( 'to' ));
			$cc = implode( ',', $this->getRecipients( 'cc' ));
			$bcc = implode( ',', $this->getRecipients( 'bcc' ));
			$headers = implode( $this->_crlf, $this->_headers );
			
			if ( !empty( $cc )) { $this->setHeader( 'Cc', $cc ); }
			if ( !empty( $ccc )) { $this->setHeader( 'Bcc', $ccc ); }
			
			return @mail( $to, $this->_subject, $this->buildBody(), $this->buildHeaders() );
		}
		
		// Protected functions
		protected function getRecipients( $type = 'to' ) {
			$addr = array();
			
			foreach( $this->_recipients as $address => $addr_type ) {
				if ( strtolower( $type ) === strtolower( $addr_type )) {
					$addr[] = $address;
				}
			}
			
			return $addr;
		}
		
		protected function replace() {
			if ( empty( $this->_replacements )) {
				return $this->_body;
			}
			
			$body = $this->_body;
			foreach( $this->_replacements as $key => $value ) {
				$body = str_replace( $key, $value, $body );
			}
			
			return $body;
		}
		
		protected function buildHeaders() {
			$parts = array();
			
			foreach ( $this->_headers as $header => $value ) {
				$parts[] = sprintf( '%s: %s', $header, $value );
			}
			return implode( $this->_crlf, $parts );
		}
		
		protected function buildBody() {
			if ( empty( $this->_attachments )) {
				return $this->_body;
			}
			
			$parts = array();
			$parts[] = '--'.$this->_boundary;
			$parts[] = sprintf( 'Content-Type: %s; charset=%s', $this->_content_type, $this->_charset );
			$parts[] = 'Content-Transfer-Encoding: '.$this->_encoding;
			$parts[] = '';
			$parts[] = $this->replace();
			$parts[] = '';
			$parts[] = '';
			
			foreach ( $this->_attachments as $attachment ) {
				$parts[] = '--'.$this->_boundary;
				$parts[] = sprintf( 'Content-Type: %s; name="%s"', $attachment->getMime(), $attachment->getName() );
				$parts[] = sprintf( 'Content-Disposition: %s; filename="%s"', $attachment->getMode(), $attachment->getName() );
				$parts[] = sprintf( 'Content-Transfer-Encoding: base64' );
				$parts[] = '';
				$parts[] = $attachment->getData();
				$parts[] = '';
			}
			$parts[] = '--'.$this->_boundary.'--';
			
			return implode( $this->_crlf, $parts );
		}
	}

	class Attachment {
		protected $_path = NULL;
		protected $_name = NULL;
		protected $_mime = NULL;
		protected $_mode = 'attachment';
		
		public static function init() { return new self(); }
		public function __construct( $path = NULL, $name = NULL, $type = NULL, $mode = 'attachment' ) {
			$this->setPath( $path );
			$this->setName( $name );
			$this->setMime( $type );
			$this->setMode( $mode );
		}
		
		public function getData() {
			$data = null;
			
			if ( file_exists( $this->_path )) {
				$data = file_get_contents( $this->_path );
				$data = chunk_split(base64_encode( $data )); // Base64 encode and split up the data so it can be sent
			} else 
				throw new Exception( sprintf( 'Speedy Mail - Error! Could not open file %s', $this->_path ));
			
			return $data;		
		}
		
		// Public functions
		public function setPath( $path ) { $this->_path = $path; return $this; }
		public function setName( $name ) { $this->_name = $name; return $this; }
		public function setMode( $mode ) { $this->_mode = $mode; return $this; }
		public function setMime( $mime ) { $this->_mime = $mime; return $this; }
		
		public function getPath() { return $this->_path; }
		public function getName() { return $this->_name; }
		public function getMode() { return $this->_mode; }
		public function getMime() {
			if ( empty( $this->_path ))
				return NULL;
			
			if ( !file_exists( $this->_path )) {
				throw new Exception( sprintf( 'Speedy Mail - Error! Could not open file %s', $this->_path ));
			}
			
			$this->_mime = mime_content_type( $this->_path );
			return $this->_mime;
		}
	}

	if( !function_exists( 'mime_content_type' )) { // The function may not exist...if that is the case we need to declare it
		function mime_content_type( $filename ) {
		    $mime_types = array(
				// Misc / Most used
		        'txt' => 'text/plain',
		        'htm' => 'text/html',
		        'html' => 'text/html',
		        'php' => 'application/x-httpd-php',
		        'css' => 'text/css',
		        'js' => 'application/javascript',
		        'json' => 'application/json',
		        'xml' => 'application/xml',
		        'swf' => 'application/x-shockwave-flash',
		        'flv' => 'video/x-flv',
		
		        // Images
		        'png' => 'image/png',
		        'jpe' => 'image/jpeg',
		        'jpeg' => 'image/jpeg',
		        'jpg' => 'image/jpeg',
		        'gif' => 'image/gif',
		        'bmp' => 'image/bmp',
		        'ico' => 'image/vnd.microsoft.icon',
		        'tiff' => 'image/tiff',
		        'tif' => 'image/tiff',
		        'svg' => 'image/svg+xml',
		        'svgz' => 'image/svg+xml',
		
		        // Archives
		        'zip' => 'application/zip',
		        'rar' => 'application/x-rar-compressed',
		        'exe' => 'application/x-msdownload',
		        'msi' => 'application/x-msdownload',
		        'cab' => 'application/vnd.ms-cab-compressed',
		
		        // Audio / Video
		        'mp3' => 'audio/mpeg',
		        'qt' => 'video/quicktime',
		        'mov' => 'video/quicktime',
		
		        // Adobe
		        'pdf' => 'application/pdf',
		        'psd' => 'image/vnd.adobe.photoshop',
		        'ai' => 'application/postscript',
		        'eps' => 'application/postscript',
		        'ps' => 'application/postscript',
		
		        // MS Office
		        'doc' => 'application/msword',
		        'rtf' => 'application/rtf',
		        'xls' => 'application/vnd.ms-excel',
		        'xlsx' => 'application/vnd.ms-excel',
		        'ppt' => 'application/vnd.ms-powerpoint',
		
		        // Open Office
		        'odt' => 'application/vnd.oasis.opendocument.text',
		        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
		    );
		
		    $ext = strtolower( array_pop( explode( '.',$filename )));
		    if ( array_key_exists( $ext, $mime_types )) {
		        return $mime_types[$ext];
		    } elseif ( function_exists('finfo_open' )) {
		        $finfo = finfo_open( FILEINFO_MIME );
		        $mimetype = finfo_file($finfo, $filename);
		        finfo_close($finfo);
				
		        return $mimetype;
		    } else {
		        return 'application/octet-stream';
		    }
		}
	}
?>