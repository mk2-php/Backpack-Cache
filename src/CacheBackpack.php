<?php

/**
 * ===================================================
 * 
 * PHP Framework "Mk2"
 *
 * CacheBackpack
 * 
 * URL : https://www.mk2-php.com/
 * 
 * Copylight : Nakajima-Satoru 2021.
 *           : Sakaguchiya Co. Ltd. (https://www.teastalk.jp/)
 * 
 * ===================================================
 */

namespace mk2\backpack_cache;

use Mk2\Libraries\Backpack;
use mk2\backpack_encrypt\EncryptBackpack;

class CacheBackpack extends Backpack{

	/**
	 *  mode 	: Cache method.
	 * 	file		= file cache(Automatically generated in the temporary directory)
	 * 	memory	= Cache in shared memory (requires APCu module)
	 */

	private const MODE_FILE="file";
	private const MODE_MEMORY="memory";

	public $mode=self::MODE_FILE; 
	public $publics=true;

	# name : Cache space name

	public $name="cache";

	# limit : Default expiration time when caching files.
	public $limit=0;

	public $encrypt=[
		"encAlgolizum"=>"aes-256-cbc",
		"encSalt"=>"cache123456789***********************",
		"encPassword"=>"cachepass123456789**********************",
	];

	public $fileTmp=MK2_PATH_TEMPORARY;
	public $directoryPrivate="_private";
	public $directoryPublic="_public";

	/**
	 * __construct
	 */
	public function __construct(){
		parent::__construct();
		
		if(!empty($this->alternativeEncrypt)){
			$this->Encrypt=new $this->alternativeEncrypt();
		}
		else{
			$this->Encrypt=new EncryptBackpack();
		}

	}

	/**
	 * public
	 */
	public function public(){
		$this->publics=true;
		return $this;
	}

	/**
	 * private
	 */
	public function private(){
		$this->publics=false;
		return $this;
	}

	/**
	 * read
	 * @param string $name = null
	 */
	public function read($name=null){

		$get=$this->_read($name);

		if($name){

			if($this->mode==self::MODE_FILE){

				if(!empty($get[$name])){
					return $get[$name];
				}
				else{
					return null;					
				}

			}
			else if($this->mode==self::MODE_MEMORY){
				return $get;
			}
		}
		else
		{
			return $get;
		}
	}

	/**
	 * flash
	 * @param string $name
	 */
	public function flash($name){
		$out=$this->read($name);
		$this->delete($name);
		return $out;
	}

	/**
	 * write
	 * @param string $name
	 * @param $data
	 */
	public function write($name,$data){
		$get[$name]=$data;
		$res=$this->_write($name,$get);
	}

	/**
	 * delete
	 * @param string $name = null
	 */
	public function delete($name=null){

		if($name){
			return $this->_delete($name);
		}
		else
		{
			$this->_delete();
		}
	}
	
	/**
	 * clear
	 */
	public function clear(){
		$this->_delete();
	}

	/**
	 * getMemoryInfo
	 */
	public function getMemoryInfo(){
		return apcu_cache_info();
	}
	
	/**
	 * getMemoryUsed
	 */
	public function getMemoryUsed($errMsged=false){

		if($this->mode==self::MODE_MEMORY){
			try{

				apcu_fetch($this->name."_public");
				return true;

			}catch(\Error $e){
				if($errMsged){
					return $e;
				}
				else
				{
					return false;
				}
			}
		}
	}

	/**
	 * setMemoryClear
	 */
	public function setMemoryClear($full=false){

		if($this->publics){
			if($full){
				$memoryPath=$this->name."_public";
			}
		}
		else
		{
			$memoryPath=$this->name."_private_".$this->_getPrivateCacheId();
		}

		apcu_delete($memoryPath);
	}

	/**
	 * (private) _write
	 */
	private function _write($name,$value){

		if($this->mode==self::MODE_FILE){
			$this->_writeFile($name,$value);
		}
		else if($this->mode==self::MODE_MEMORY){
			$this->_writeMemory($name,$value);
		}

	}

	/**
	 * (private) _writeFile
	 */
	private function _writeFile($name,$value){

		if(!empty($this->encrypt)){
			$value=$this->Encrypt->encode($value,$this->encrypt);
		}
		else
		{
			$value=json_enc($value);
		}

		if($this->publics){
			//public cache write
			$filedir=$this->fileTmp."/".$this->name."/".$this->directoryPublic;
			$filename=$this->fileTmp."/".$this->name."/".$this->directoryPublic."/".hash("sha256",$name);
			
			if(!is_dir($filedir)){
				@mkdir($filedir,0775,true);
			}

			$fs=fopen($filename,"w");
			fputs($fs,$value);
			fclose($fs);
		}
		else
		{
			//private cache write
			$filedir=$this->fileTmp."/".$this->name."/".$this->directoryPrivate."/".$this->_getPrivateCacheId();
			$filename=$this->fileTmp."/".$this->name."/".$this->directoryPrivate."/".$this->_getPrivateCacheId()."/".hash("sha256",$name);
			
			if(!is_dir($filedir)){
				@mkdir($filedir,0775,true);
			}

			$fs=fopen($filename,"w");
			fputs($fs,$value);
			fclose($fs);
		}

	}

	
	/**
	 * (private) _writeMemory
	 */
	private function _writeMemory($name,$value){

		if($this->publics){
			$memoryPath=$this->name."_public";
		}
		else
		{
			$memoryPath=$this->name."_private_".$this->_getPrivateCacheId();
		}

		$get=$this->_read();

		$get[$name]=$value[$name];

		if(!empty($this->encrypt)){
			$get=$this->Encrypt->encode($get,$this->encrypt);
		}

		$res=apcu_store($memoryPath,$get,$this->limit);

	}

	/**
	 * (private) _read
	 */
	private function _read($name=null){

		if($this->mode==self::MODE_FILE){
			return $this->_readFile($name);
		}
		else if($this->mode==self::MODE_MEMORY){
			return $this->_readMemory($name);
		}

	}

	/**
	 * (private) _readFile
	 */
	private function _readFile($name=null){

		$get=null;

		if($this->publics){
			if($name){
				$filename=$this->fileTmp."/".$this->name."/".$this->directoryPublic."/".hash("sha256",$name);
				if(file_exists($filename)){
					$fs=fopen($filename,"r");
					$get=fgets($fs);
					fclose($fs);

					if(!empty($this->encrypt)){
						$get=$this->Encrypt->decode($get,$this->encrypt);
					}
					else
					{
						$get=json_dec($get);
					}
				}
			}
			else
			{
				$filedir=$this->fileTmp."/".$this->name."/".$this->directoryPublic;
				$list=glob($filedir."/*");
				foreach($list as $l_){
					$n=basename($l_);
					$fs=fopen($l_,"r");
					$buff=fgets($fs);
					fclose($fs);

					if(!empty($this->encrypt)){
						$buff=$this->Encrypt->decode($buff,$this->encrypt);
					}
					else
					{
						$buff=json_dec($buff);
					}

					if($buff){
						foreach($buff as $key=>$value){
							$get[$key]=$value;
						}
					}
				}
			}
		}
		else
		{
			if($name){
				$filename=$this->fileTmp."/".$this->name."/".$this->directoryPrivate."/".$this->_getPrivateCacheId()."/".hash("sha256",$name);
				if(file_exists($filename)){
					$fs=fopen($filename,"r");
					$get=fgets($fs);
					fclose($fs);

					if(!empty($this->encrypt)){
						$get=$this->Encrypt->decode($get,$this->encrypt);
					}
					else
					{
						$get=json_dec($get);
					}
				}
			}
			else
			{
				$filedir=$this->fileTmp."/".$this->name."/".$this->directoryPrivate."/".$this->_getPrivateCacheId();
				$list=glob($filedir."/*");
				foreach($list as $l_){
					$n=basename($l_);
					$fs=fopen($l_,"r");
					$buff=fgets($fs);
					fclose($fs);

					if(!empty($this->encrypt)){
						$buff=$this->Encrypt->decode($buff,$this->encrypt);
					}
					else
					{
						$buff=json_dec($get);
					}

					foreach($buff as $key=>$value){
						$get[$key]=$value;
					}

				}
			}
		}

		return $get;

	}

	/**
	 * (private) _readMemory
	 */
	private function _readMemory($name=null){

		if($this->publics){
			$memoryPath=$this->name."_public";
		}
		else
		{
			$memoryPath=$this->name."_private_".$this->_getPrivateCacheId();
		}

		$get=apcu_fetch($memoryPath);

		if(!empty($this->encrypt)){
			$get=$this->Encrypt->decode($get,$this->encrypt);
		}

		if($name){

			if(!empty($get[$name])){
				return $get[$name];
			}

		}
		else
		{
			return $get;
		}

	}

	/**
	 * (private) _delete
	 */
	private function _delete($name=null){

		if($this->mode==self::MODE_FILE){
			$this->_deleteFile($name);
		}
		else if($this->mode==self::MODE_MEMORY){
			$this->_deleteMemory($name);
		}

	}

	/**
	 * (private) _deleteFile
	 */
	private function _deleteFile($name=null){

		if($name){
			if($this->publics){
				$filepath=$this->fileTmp."/".$this->name."/".$this->directoryPublic."/".hash("sha256",$name);
				@unlink($filepath);
			}
			else
			{
				$filepath=$this->fileTmp."/".$this->name."/".$this->directoryPrivate."/".$this->_getPrivateCacheId()."/".hash("sha256",$name);
				@unlink($filepath);
			}
		}
		else
		{
			if($this->publics){
				$filedir=$this->fileTmp."/".$this->name."/".$this->directoryPublic;
				$list=glob($filedir."/*");
				foreach($list as $l_){
					unlink($l_);
				}
			}
			else{
				$filedir=$this->fileTmp."/".$this->name."/".$this->directoryPrivate."/".$this->_getPrivateCacheId();
				$list=glob($filedir."/*");
				foreach($list as $l_){
					unlink($l_);
				}
			}
		}
	}

	/**
	 * (private) _deleteMemory
	 */
	private function _deleteMemory($name=null){

		$get=$this->read();

		if($this->publics){
			$memoryPath=$this->name."_public";
		}
		else
		{
			$memoryPath=$this->name."_private_".$this->_getPrivateCacheId();
		}

		if($name){

			unset($get[$name]);

		}
		else
		{
			$get=[];
		}

		if(!empty($this->encrypt)){
			$get=$this->Encrypt->encode($get,$this->encrypt);
		}

		apcu_store($memoryPath,$get,$this->limit);

	}

	/**
	 * (private) _getPrivateCacheId
	 */
	private function _getPrivateCacheId(){

		if($this->publics){
			return null;
		}

		if(!empty($_COOKIE["CACHEID"])){
			return $_COOKIE["CACHEID"];
		}
		else
		{
			$cacheId=hash("sha256",time()."|_CDA_");
			setcookie("CACHEID",$cacheId,0);
			return $cacheId;
		}
	}


}