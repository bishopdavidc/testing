<?php
//if(!defined('IN_ID'))die('You are not allowed to access to this page.');

/**
 * mp3_id3v10.php
 *--------------------------------------------------------------------
 *
 * Manage ID3V1.1 Tags
 * Major differences since 1.0 are the track number and comment is 28 in
 * length (before it was 30)
 *
 *--------------------------------------------------------------------
 * Revision History
 * V1.00	24 jul	2005	Jean-Sebastien Goupil
 *--------------------------------------------------------------------
 * $Id$
 *--------------------------------------------------------------------
 * Copyright (C) Jean-Sebastien Goupil
 * http://other.lookstrike.com/
 */
class mp3_id3v11 {
	private $file_loaded;
	private $filename,$fp;
	private $tag_readable;
	private $tag;
	private $remove_tag;
	private $genres = array(
		'Blues','Classic Rock','Country','Dance','Disco','Funk','Grunge', 
		'Hip-Hop','Jazz','Metal','New Age','Oldies','Other','Pop','R&B', 
		'Rap','Reggae','Rock','Techno','Industrial','Alternative','Ska', 
		'Death Metal','Pranks','Soundtrack','Euro-Techno','Ambient', 
		'Trip-Hop','Vocal','Jazz+Funk','Fusion','Trance','Classical', 
		'Instrumental','Acid','House','Game','Sound Clip','Gospel', 
		'Noise','Alt. Rock','Bass','Soul','Punk','Space','Meditative', 
		'Instrumental Pop','Instrumental Rock','Ethnic','Gothic', 
		'Darkwave','Techno-Industrial','Electronic','Pop-Folk', 
		'Eurodance','Dream','Southern Rock','Comedy','Cult','Gangsta Rap', 
		'Top 40','Christian Rap','Pop/Funk','Jungle','Native American', 
		'Cabaret','New Wave','Psychedelic','Rave','Showtunes','Trailer', 
		'Lo-Fi','Tribal','Acid Punk','Acid Jazz','Polka','Retro', 
		'Musical','Rock & Roll','Hard Rock','Folk','Folk/Rock', 
		'National Folk','Swing','Fast-Fusion','Bebob','Latin','Revival', 
		'Celtic','Bluegrass','Avantgarde','Gothic Rock','Progressive Rock', 
		'Psychedelic Rock','Symphonic Rock','Slow Rock','Big Band', 
		'Chorus','Easy Listening','Acoustic','Humour','Speech','Chanson', 
		'Opera','Chamber Music','Sonata','Symphony','Booty Bass','Primus', 
		'Porn Groove','Satire','Slow Jam','Club','Tango','Samba', 
		'Folklore','Ballad','Power Ballad','Rhythmic Soul','Freestyle', 
		'Duet','Punk Rock','Drum Solo','A Cappella','Euro-House','Dance Hall',
		// Extension
		'Goa','Drum & Bass','Club-House','Hardcore','Terror','Indie','BritPop',
		'Negerpunk','Polsk Punk','Beat','Christian Gangsta Rap','Heavy Metal',
		'Black Metal','Crossover','Contemporary Christian','Christian Rock',
		'Merengue','Salsa','Trash Metal','Anime','JPop','Synthpop');
	private $title,$artist,$album,$year,$comment,$track,$genre;

	function __construct(){
		$this->file_loaded = false;
		$this->tag_readable = false;
		$this->remove_tag = false;
	}

	function __destruct(){
		if($this->fp)
			fclose($this->fp);
	}

	/**
	 * Loads $file file
	 *
	 * @param string $file path to file
	 * @return bool Success
	 */
	public function load_file($file){
		$this->remove_tag = false;
		if($this->file_loaded == false){
			$this->fp = fopen($file,'rb');
			if($this->fp){
				$this->filename = $file;
				$this->file_loaded = true;
				$this->read_tag();
				return true;
			}
			else
				return false;
		}
		else
			return false;
	}

	/**
	 * Reads the Tags from the file
	 *
	 * @return bool
	 */
	public function read_tag(){
		if($this->file_loaded == true){
			fseek($this->fp, filesize($this->filename)-128);
			$this->tag = fread($this->fp,128);
			if(substr($this->tag,0,3)=='TAG'){
				$this->tag_readable = true;
				return true;
			}
			else{
				$this->tag_readable = false;
				return false;
			}
		}
		else
			return false;
	}

	/**
	 * Gets all the tags as an associative array
	 *
	 * @return string[]
	 */
	public function get_tag(){
		if($this->tag_readable == true){
			$this->title = trim(substr($this->tag,3,30));
			$this->artist = trim(substr($this->tag,33,30));
			$this->album = trim(substr($this->tag,63,30));
			$this->year = trim(substr($this->tag,93,4));
			$this->comment = trim(substr($this->tag,97,28));
			$this->track = ord(substr($this->tag,126,1));
			$this->genre = ord(substr($this->tag,127,1));
			$genre = (isset($this->genres[$this->genre]))?$this->genres[$this->genre]:'Unknown';
			$temp = array('title'=>$this->title,'artist'=>$this->artist,'album'=>$this->album,'year'=>$this->year,'comment'=>$this->comment,'track'=>$this->track,'genre'=>$genre);
			return $temp;
		}
		else
			return NULL;
	}

	/**
	 * Set tag
	 *
	 * @param string $title
	 * @param string $artist
	 * @param string $album
	 * @param string $year
	 * @param string $comment
	 * @param int $track
	 * @param int $genre
	 */
	public function set_tag($title,$artist,$album,$year,$comment,$track,$genre){
		$this->remove_tag = false;
		$this->title = substr($title,0,30);
		$this->artist = substr($artist,0,30);
		$this->album = substr($album,0,30);
		$this->year = substr($year,0,4);
		$this->comment = substr($comment,0,28);
		$this->track = intval($track);
		$this->genre = intval($genre);
	}

	/**
	 * Creates tag
	 */
	private function create_tag(){
		// We close first (for writing)
		if($this->fp)
			fclose($this->fp);
		$this->fp = fopen($this->filename,'r+b');
		fseek($this->fp,filesize($this->filename));
		$val = 'TAG';
		for($i=0;$i<124;$i++)
			$val .= chr(0);
		$val .= chr(255);
		fputs($this->fp,$val);
		fclose($this->fp);
		clearstatcache();
		$this->file_loaded = false;
		$this->load_file($this->filename);
	}

	/**
	 * Writes the tag to the file according to the value passed to set_tag
	 *
	 * @return bool
	 */
	public function write_file(){
		if($this->file_loaded == true){
			if($this->remove_tag == true){
				if($this->fp)
					fclose($this->fp);
				$this->fp = fopen($this->filename,'r+b');
				if($this->tag_readable == true){
					rewind($this->fp);
					ftruncate($this->fp,filesize($this->filename)-128);
				}
				fclose($this->fp);
				$this->file_loaded = false;
				$this->load_file($this->filename);
				return true;
			}
			else{
				if($this->tag_readable == false)
					$this->create_tag();

				if($this->fp)
					fclose($this->fp);

				$filesize_fp = filesize($this->filename);

				$this->fp = fopen($this->filename,'r+b');

				// TITLE
				fseek($this->fp,$filesize_fp-128 +3);
				fputs($this->fp,str_pad($this->title,30,chr(0),STR_PAD_RIGHT));

				// ARTIST
				fseek($this->fp,$filesize_fp-128 +33);
				fputs($this->fp,str_pad($this->artist,30,chr(0),STR_PAD_RIGHT));

				// ALBUM
				fseek($this->fp,$filesize_fp-128 +63);
				fputs($this->fp,str_pad($this->album,30,chr(0),STR_PAD_RIGHT));

				// YEAR
				fseek($this->fp,$filesize_fp-128 +93);
				fputs($this->fp,str_pad($this->year,4,chr(0),STR_PAD_RIGHT));

				// COMMENT
				fseek($this->fp,$filesize_fp-128 +97);
				fputs($this->fp,str_pad($this->comment,29,chr(0),STR_PAD_RIGHT));

				// TRACK
				fseek($this->fp,$filesize_fp-128 +126);
				fputs($this->fp,chr($this->track));

				// GENRE
				fseek($this->fp,$filesize_fp-128 +127);
				fputs($this->fp,chr(($this->genre==NULL)?255:$this->genre));

				fclose($this->fp);
				clearstatcache();
				$this->file_loaded = false;
				$this->load_file($this->filename);
	
				return true;
			}
		}
		else
			return false;
	}

	/**
	 * Removes completely the tag from the file
	 */ 
	public function remove_tag(){
		$this->set_tag('','','','','','',0,0);
		$this->remove_tag = true;
	}

	/**
	 * Get all Genres available
	 *
	 * @return string[]
	 */
	public function getGenres(){
		return $this->genres;
	}
};
?>