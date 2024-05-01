<?php // {"theme":"dark","editor_theme":"chrome","editor_font_size":"14","enable_superuser":"1","login_email":"digishops","login_password":"$2y$10$8/PS8Fj7azan4..UxNVuBeoqrn2wy/8egTaQfiaR/0w6GOAk3ReGW","font_size":"14"}

 
session_start();

date_default_timezone_set('UTC');

define('IS_WINDOWS', 		( strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' ) );
define('SELF_URL', 			strtok( $_SERVER['REQUEST_URI'], '?' ) );
define('DOC_ROOT', 			rtrim( str_replace( DIRECTORY_SEPARATOR, '/', $_SERVER["DOCUMENT_ROOT"] ), '/' ) );
define('MAIN_ROOT', 		get_root() );
define('PROJECT_ROOT', 		getcwd());
define('AUTHOR', 			'Digishops');
define('AUTHOR_URL', 		'https://digishops.xyz');
define('PRODUCT_NAME', 		'FM V2');

$global_settings = read_settings();

if( isset( $_GET['path'] ) ){
	if( $global_settings->enable_superuser ){
		define('TREE_NODES_ARRAY', get_paths( $_GET['path'], true ) );
	} else {
		define('TREE_NODES_ARRAY', json_encode( array_values( array_diff( get_paths( $_GET['path'] ), get_paths( PROJECT_ROOT ) ) ) ) );
	}
} else {
	define('TREE_NODES_ARRAY', get_paths( PROJECT_ROOT, true ) );
}

function pr( $a ){
	echo '<pre>';
	print_r( $a );
	echo '</pre>';
}

function get_paths( $path, $return_json = false ){
	$array_paths = explode( DIRECTORY_SEPARATOR,trim( str_replace( MAIN_ROOT . DIRECTORY_SEPARATOR, '', $path ), DIRECTORY_SEPARATOR ) );
	return $return_json ? json_encode( $array_paths ) : $array_paths;
}

function update_settings( $new_settings ){
	$file_settings = DOC_ROOT . SELF_URL;
	$file_lines = file( $file_settings );
	$file_lines[0] = "<?php // $new_settings\n";
	file_put_contents( $file_settings, implode( $file_lines ) );
}

function read_settings(){
	$file_settings = DOC_ROOT . SELF_URL;
	$file_lines = file( $file_settings );
	$settings = str_replace( '<?php // ', '', $file_lines[0] );
	$settings = json_decode( trim( $settings ) );
	return (object) $settings;
}

function get_directory_heirarchy( $path ){
	global $global_settings;
	
	$directories_levels = array();
	$directories = explode( DIRECTORY_SEPARATOR , $path );
	$project_root = get_paths( PROJECT_ROOT );
	array_pop( $project_root );
	$not_allowed = ! $global_settings->enable_superuser ? array_values( array_merge( get_paths( MAIN_ROOT ), $project_root ) ) : array() ;
	
	$count = 0;
	$length = count( $directories );
	foreach ( $directories as $i => $directory ){
		$active = $count == $length - 1 ? 'text-danger': '';
		if( in_array( $directory, $not_allowed ) ){
			$directories_levels[] = sprintf(
				'<span class="breadcrumb-item ' . $active . '">%s</span>',
				$directory
			);
		} else {
			$directories_levels[] = sprintf(
				'<a href="%s" class="breadcrumb-item text-primary open-directory ellipsis ' . $active . '" title="%s">%s</a>',
				implode(DIRECTORY_SEPARATOR, array_slice($directories, 0, $i + 1)),
				$directory, $directory
			);
		}
		
		$count++;
	}
	
	return '<nav class="breadcrumb">' . implode( $directories_levels ) . '</nav>';
}

function get_root(){
	if( IS_WINDOWS ){
		if ( strpos( DOC_ROOT, ':' ) !== false ){
			$root = ucfirst( substr( DOC_ROOT, 0, strpos( DOC_ROOT,':' ) + 1) );
		} else { 
			$root = ucfirst( DOC_ROOT );
		}
	} else {
		$root = "/"; /* Linux */
	}
	return $root;
}

/* Used for compressing result string for AJAX response */
function compress_output( $buffer ){
	$search = array(
		"/\/\*(.*?)\*\/|[\t\r\n]/s" => "",
		"/ +\{ +|\{ +| +\{/" => "{",
		"/ +\} +|\} +| +\}/" => "}",
		"/ +: +|: +| +:/" => ":",
		"/ +; +|; +| +;/" => ";",
		"/ +, +|, +| +,/" => ","
	);
	$buffer = preg_replace(array_keys($search), array_values($search), $buffer);
	
	return $buffer;
}

function format_bytes($bytes, $precision = 2){ 
    $units = array('b', 'kb', 'mb', 'gb', 'tb'); 
    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 
	$bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow]; 
}

function ine( $key ){
	return isset( $_POST[$key]) && ! empty( $_POST[$key] ) ? true : false; 
}

function strip_specials($string) {
   $string = str_replace(' ', '-', $string);
   $string = preg_replace('/[^A-Za-z0-9\-]/', '', $string);
   return preg_replace('/-+/', '-', $string);
}

function read_file( $file_path ){
	if( file_exists( $file_path ) ){
		$handle = fopen( $file_path, 'r' );
		$result = fread( $handle, filesize( $file_path ) + 1 );
		fclose( $handle );
		if( $result ){
			return $result;
		}
		return '';
	}
	return 0;
}

function update_file( $file_path, $data ){
	if( file_exists( $file_path ) ){
		$handle = fopen( $file_path, 'w' );
		$result = fwrite( $handle, $data );
		fclose( $handle );
		return 1;
	}
	return 0;
}

function delete_file( $file_path ){
	if ( file_exists( $file_path ) ){
        @chmod( $file_path, 0755 );
        if ( is_dir( $file_path ) ){
            $handle = opendir( $file_path );
            while( $aux = readdir( $handle ) ){
                if ( $aux != "." && $aux != ".." ) delete_file( $file_path . "/" . $aux );
            }
            @closedir( $handle );
            return rmdir( $file_path );
        } else { 
			return unlink( $file_path );
		}
    }
	return 0;
}

function copy_file( $orig, $dest ){
    $ok = true;
    if ( file_exists( $orig ) ){
        if ( is_dir( $orig ) ){
            mkdir( $dest, 0755 );
            $handle = opendir( $orig );
            while( ( $aux = readdir( $handle ) ) && ( $ok ) ){
                if ( $aux != "." && $aux != ".." ){
					$ok = copy_file( $orig . "/" . $aux, $dest . "/" . $aux );
				}
            }
            @closedir( $handle );
        } else {
			$ok = copy( (string) $orig, (string) $dest );
		}
    }
    return $ok;
}

function move_file( $orig, $dest ){
    return rename( (string) $orig, (string) $dest);
}

class fs {
    protected $base = null;

    public function __construct($base){
        $this->base = $this->real($base);
        if(!$this->base){ 
			throw new Exception('Base directory does not exist'); 
		}
    }
	protected function real($path){
        $temp = $path;
        if(!$temp){ throw new Exception('Path does not exist: ' . $path); }
        if($this->base && strlen($this->base)){
            if(strpos($temp, $this->base) !== 0){ throw new Exception('Path is not inside base ('.$this->base.'): ' . $temp); }
        }
        return $temp;
    }
    protected function path($id){
        $id = str_replace('/', DIRECTORY_SEPARATOR, $id);
        $id = trim($id, DIRECTORY_SEPARATOR);
        $id = $this->real($this->base . DIRECTORY_SEPARATOR . $id);
        return $id;
    }
    protected function id($path){
        $path = $this->real($path);
        $path = substr($path, strlen($this->base));
        $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        $path = trim($path, '/');
        return strlen($path) ? $path : '/';
    }
    public function lst($id, $with_root = false){
        $dir = $this->path($id);
        $lst = @scandir($dir);
        if(!$lst){
			throw new Exception('Could not list path: ' . $dir); 
		}
        $res = array();
        foreach($lst as $item){
            if($item == '.' || $item == '..' || $item === null){ 
				continue; 
			}
            if(is_dir($dir . DIRECTORY_SEPARATOR . $item)){
                $id = utf8_encode($this->id($dir . DIRECTORY_SEPARATOR . $item));
                $href = $dir . DIRECTORY_SEPARATOR . $item;
				$res[] = array(
					'text' => utf8_encode($item), 
					'children' => true,
					'a_attr' => array(
						'href' => str_replace(DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $href),
						'class' => 'open-directory font-weight-bold',
					), 
					'id' => $id, 
					'icon' => 'fa fa-folder text-muted');
            }
        }
        if($with_root && $this->id($dir) === '/'){
            $text = utf8_encode(basename($this->base));
            $res = array(array(
				'text' => $text, 
				'children' => $res,
				'a_attr' => array(
					'href' => $this->base,
					'class' => 'open-directory',
				),
				'id' => '/', 
				'icon'=> 'fa fa-folder text-muted', 
				'state' => array(
					'opened' => true, 'disabled' => true
				)
			));
        }
        return $res;
    }
}

/**
 * Class to work with zip files (using ZipArchive)
 */
class archive {
    private $zip;

    public function __construct(){
        $this->zip = new ZipArchive();
    }

    /**
     * Create archive with name $filename and files $files (RELATIVE PATHS!)
     * @param string $filename
     * @param array|string $files
     * @return bool
     */
    public function create($filename, $files){
        $res = $this->zip->open($filename, ZipArchive::CREATE);
        if ($res !== true) {
            return false;
        }
        if (is_array($files)) {
            foreach ($files as $f) {
                if (!$this->addFileOrDir($f)) {
                    $this->zip->close();
                    return false;
                }
            }
            $this->zip->close();
            return true;
        } else {
            if ($this->addFileOrDir($files)) {
                $this->zip->close();
                return true;
            }
            return false;
        }
    }

    /**
     * Extract archive $filename to folder $path (RELATIVE OR ABSOLUTE PATHS)
     * @param string $filename
     * @param string $path
     * @return bool
     */
    public function unzip($filename, $path){
        $res = $this->zip->open($filename);
        if ($res !== true) {
            return false;
        }
        if ($this->zip->extractTo($path)) {
            $this->zip->close();
            return true;
        }
        return false;
    }

    /**
     * Add file/folder to archive
     * @param string $filename
     * @return bool
     */
    private function addFileOrDir($filename){
        if (is_file($filename)) {
            return $this->zip->addFile($filename);
        } elseif (is_dir($filename)) {
            return $this->addDir($filename);
        }
        return false;
    }

    /**
     * Add folder recursively
     * @param string $path
     * @return bool
     */
    private function addDir($path){
        if (!$this->zip->addEmptyDir($path)) {
            return false;
        }
        $objects = scandir($path);
        if (is_array($objects)) {
            foreach ($objects as $file) {
                if ($file != '.' && $file != '..') {
                    if (is_dir($path . '/' . $file)) {
                        if (!$this->addDir($path . '/' . $file)) {
                            return false;
                        }
                    } elseif (is_file($path . '/' . $file)) {
                        if (!$this->zip->addFile($path . '/' . $file)) {
                            return false;
                        }
                    }
                }
            }
            return true;
        }
        return false;
    }
}

function unzip_file($zip_name, $current_path, $destination_path){
	$zip = zip_open($current_path.$zip_name);
	if($zip){
		while ($zip_entry = zip_read($zip)){
			if(zip_entry_filesize($zip_entry)){
				$complete_path = dirname(zip_entry_name($zip_entry));
				$complete_name = zip_entry_name($zip_entry);
				if(!file_exists($destination_path.$complete_path)){
					$tmp = '';
					foreach(explode('/',$complete_path) as $k){
						$tmp .= $k.'/';
						if(!file_exists($destination_path.$tmp)){
							@mkdir($destination_path.$tmp, 0755);
							@chmod($destination_path.$tmp, 0755 );
						}
					}
				}
				
				if(zip_entry_open($zip, $zip_entry, "r")){
					if($fd = @fopen($destination_path.$complete_name, 'w')){
						fwrite($fd, zip_entry_read($zip_entry, zip_entry_filesize($zip_entry)));
						fclose($fd);
						@chmod($destination_path, 0755);
					}
					zip_entry_close($zip_entry);
				} else {
					echo "zip_entry_open($zip,$zip_entry) error<br>";
				}
			}
		}
		zip_close($zip);
	}
}

/* View Image File( */
if( isset( $_GET['action'] ) && $_GET['action'] == 'view-photo' &&
	isset( $_GET['filepath'] ) && ! empty( $_GET['filepath'] ) 
){
	$file_path = urldecode( $_GET['filepath'] );
	
	if( is_readable( $file_path ) ){
		$info = getimagesize( $file_path );
		if ( $info !== FALSE ) {
			header("Content-type: {$info['mime']}");
			readfile( $file_path ); 
		}
	}
}


/* Download File( */
if( isset( $_GET['action'] ) && $_GET['action'] == 'download' &&
	isset( $_GET['filepath'] ) && ! empty( $_GET['filepath'] ) 
){
	
	$file_path = urldecode( $_GET['filepath'] );
	
	if ( file_exists( $file_path ) ){
		header('Content-Description: File Transfer');
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename=' . basename( $file_path ) );
		
		ob_clean();
		flush();
		readfile( $file_path );
		exit();
	}
}
	
/* Logout handler */
if( isset( $_GET['logout'] ) && $_GET['logout'] == 1 ){
	unset( $_SESSION['advanced-php-file-manager']['email'] );
	unset( $_SESSION['advanced-php-file-manager']['is_logged_in'] );
	
	header('Location: ' . SELF_URL);
	exit();
}

if( isset( $_POST ) && ! empty( $_POST ) ){
	$response = array(
		'status'	=> 1, 
		'content'	=> '', 
	);
	
	/* Edit Settings */
	if( isset( $_POST['action'] ) && $_POST['action'] == 'edit-settings' ){
		foreach( $_POST as $key => $config ){
			if( $key != 'action' && $key != 'old_password' && $key != 'new_password' && $key != 'repeat_new_password' ){
				$global_settings->$key = $_POST[$key] ? $_POST[$key] : 0;
			}
		}
		if( isset( $_POST['old_password'] ) && ! empty( $_POST['old_password'] ) && 
			isset( $_POST['new_password'] ) && ! empty( $_POST['new_password'] ) && 
			isset( $_POST['repeat_new_password'] ) && ! empty( $_POST['repeat_new_password'] )
		){
			if( ! password_verify( $_POST['old_password'], $global_settings->login_password ) ){
				$response['content'] = 'Incorrect old password';
				$response['status'] = 0;
				echo json_encode( $response );
				exit();
				
			} else if( $_POST['new_password'] != $_POST['repeat_new_password'] ){
				$response['content'] = 'New password do not match';
				$response['status'] = 0;
				echo json_encode( $response );
				exit();
				
			} else {
				$global_settings->login_password = password_hash( $_POST['new_password'], PASSWORD_BCRYPT );
			}
		}
		
		update_settings( json_encode( (array) $global_settings ) );
		$response['content'] = 'Settings successfullly updated';
	}
		
	/* Login handler */
	if( isset( $_POST['action'] ) && $_POST['action'] == 'login' ){
		if( isset( $_POST['email'] ) && isset( $_POST['password'] ) && $_POST['email'] == $global_settings->login_email && password_verify( $_POST['password'], $global_settings->login_password ) ){
			$_SESSION['advanced-php-file-manager']['email'] = $_POST['email'];
			$_SESSION['advanced-php-file-manager']['is_logged_in'] = 1;
		} else {
			$response['content'] = '?login-error=' . urlencode('Invalid username or password');
		}
		
		header('Location: ' . SELF_URL . $response['content']);
		exit();
	}
	
	/* Do not allow posting thru API if not logged-in */
	if( ! isset( $_SESSION['advanced-php-file-manager']['is_logged_in'] ) ){
		echo json_encode( $response );
		exit();
	}
	
	/* Convert forward slash to backward slash if on Windows FS */
	if( IS_WINDOWS && ine('path') ){
		$_POST['path'] = str_replace('/', DIRECTORY_SEPARATOR, $_POST['path']);
	}
	

	if( ! $global_settings->enable_superuser && ine('path') && strpos($_POST['path'], PROJECT_ROOT) === false ){
		$response['status'] = 0;
		$response['content'] .= '<div class="mt-3"><span class="text-danger font-weight-bold">Error:</span> Target file / directory is either out of scope or does not exist.</div>';
		echo json_encode( $response );
		exit();
	}
	
	/* Return json formatted directories to be used for building the directory tree */
	if( isset( $_POST['action'] ) && $_POST['action'] == 'tree' && isset( $_POST['method'] ) && isset( $_POST['id'] ) ){
		if( $_POST['method'] == 'list-directory' ){
			$node = isset($_POST['id']) && $_POST['id'] !== '#' ? $_POST['id'] : DIRECTORY_SEPARATOR;
			$fs = new fs( ( $global_settings->enable_superuser ? MAIN_ROOT : PROJECT_ROOT ) );
			
			$response['content'] = $fs->lst( $node, true );
		}
		
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode( $response['content'] );
		exit();
	}
	
	/* List directory files and or folders */
	if( isset( $_POST['action'] ) && $_POST['action'] == 'list-directory' ){
		
		$current_dir = getcwd();
		$supported_archives = array('ZIP');
		$supported_images = array('JPG', 'JPEG', 'PNG', 'GIF', 'BMP', 'ICO');
		$unsupported_media = array('WEBM', 'MKV', 'FLV', 'VOB', 'OGG', 'OGV', 'AVI', 'MOV', 'WMV', 'M4V', 'MP4', 'MP3', 'MPEG', '3GP', 'M2V', 'MPG', 'MPA', 'WMA', 'WPL', 'MID', 'MIDI', 'CDA', 'AIF');
		$unsupported_documents = array('DOC', 'DOCX', 'PDF', 'XLS', 'XLSX');
		$unsupported_for_read_write = array_merge( $supported_archives, $supported_images, $unsupported_media, $unsupported_documents, array() );
		
		if( ine('path') ){
			$current_dir = urldecode( $_POST['path'] );
		}
		
		if( ! file_exists( $current_dir ) ){
			$response['content'] .= '<div class="mt-3"><span class="text-danger font-weight-bold">Error:</span> <code>' .  $current_dir . '</code> is not a a valid directory path</div>';
			
		} else {
			$files = new DirectoryIterator( $current_dir );
			$sort_icon = '<i class="fa fa-sort" aria-hidden="true"></i>';
			
			$response['content'] .= '
			<div class="mt-2 clearfix action-btns">' . get_directory_heirarchy( $current_dir ) . '
				<button class="btn btn-sm btn-link mb-3 select-all-btn"><i class="fas fa-check-circle"></i> Select All</button> 
				<button class="btn btn-sm btn-link mb-3 select-none-btn"><i class="far fa-circle"></i> Select None</button> 
				<button class="btn btn-sm btn-link mb-3 mutliple-btn" data-action="copy" data-path="' . $current_dir . '"><i class="fa fa-clipboard" aria-hidden="true"></i> Copy</button> 
				<button class="btn btn-sm btn-link mb-3 mutliple-btn" data-action="move" data-path="' . $current_dir . '"><i class="fa fa-clone" aria-hidden="true"></i> Move</button> 
				<button class="btn btn-sm btn-link mb-3 mutliple-btn" data-action="compress" data-path="' . $current_dir . '"><i class="far fa-file-archive"></i> Zip</button> 
				<button class="btn btn-sm btn-link mb-3 mutliple-btn" data-action="delete" data-path="' . $current_dir . '"><i class="fas fa-trash-alt"></i> Delete</button> 
				<form method="post" action="' . SELF_URL . '" class="upload-files-btn d-inline-block">	
					<input type="hidden" name="action" value="upload" />
					<input type="hidden" name="path" value="' . $current_dir . '" />
					<label class="btn btn-sm btn-link mb-3">
						<i class="fa fa-upload" aria-hidden="true"></i> Upload Files
						<input type = "file" name = "files[]" class = "images-data d-none" multiple />
					</label>
				</form> 
				<button class="btn btn-sm btn-link mb-3 create-btn" data-action="create-folder" data-path="' . $current_dir . '"><i class="fa fa-plus" aria-hidden="true"></i> New Folder</button> 
				<button class="btn btn-sm btn-link mb-3 create-btn" data-action="create-file" data-path="' . $current_dir . '"><i class="fa fa-plus" aria-hidden="true"></i> New File</button> 
				<button class="btn btn-sm btn-link mb-3 refresh-path-btn" data-path="' . $current_dir . '"><i class="fas fa-sync-alt"></i> Refresh</button> 
			</div>
			
			<div class="col-md-3 p-0 search-container">
				<div class="input-group input-group-sm mb-3">
					<div class="input-group-prepend">
						<span class="input-group-text"><i class="fa fa-search"></i></span>
					</div>
					<input type="text" class="form-control search-input" placeholder="Enter a keyword">
				</div>
			</div>
			
			
			<div class="table-responsive">
				<table class="table table-files table-sm table-hover search-table">
					<thead>
						<tr class="font-weight-bold" id="is-header">
							<th data-sort="string" data-sort-onload="yes" class="c-p pt-2 pb-2" width="45%">Name ' . $sort_icon . '</th>
							<th data-sort="int" class="c-p pt-2 pb-2 text-right">Filesize ' . $sort_icon . '</th>
							<th data-sort="int" class="c-p pt-2 pb-2 text-right">Last modified ' . $sort_icon . '</th>
							<th data-sort="int" class="c-p pt-2 pb-2 text-right">Perm ' . $sort_icon . '</th>
							<th class="pt-2 pb-2 text-right">Actions</th>
						</tr>
					</thead>
					<tbody>';
				
				/* List folders */
				foreach ( $files as $file ){
					if( $file->isDot() ) continue; /* Ignore dot folders */
					if( $file->isDir() ){
						$response['content'] .= '
						<tr align="right">
							<td align="left">
								<input type="checkbox" class="check-file mr-2 align-middle" id="check-file" data-filename="' . $file->getFilename() . '" />
								<a href="' . $file->getPathname() . '" title="' . $file->getFilename() . '" class="open-directory ellipsis" aria-hidden="true"><i class="fa fa-folder mr-1"></i> <strong>' . $file->getFilename() . '</strong></a> &nbsp; 
							</td>
							<td data-sort-value="0"></td>
							<td data-sort-value="' . filemtime( $file->getPathname() ) . '">' . date( 'Y/m/d H:i:s', filemtime( $file->getPathname() ) ) . '</td>
							<td>' . decoct( $file->getPerms() & 0777 ) . '</td>
							<td>
								<button type="button" class="btn btn-sm btn-link rename-file-btn" data-filename="' . $file->getFilename() . '" data-path="' . $file->getPath() . '" title="Rename">
									<i class="far fa-edit"></i>
								</button>&nbsp;
								
								<button type="button" class="btn btn-sm btn-link delete-file-btn" data-type="folder" data-filename="' . $file->getFilename() . '" data-path="' . $file->getPath() . '" data-pathname="' . $file->getPathname() . '" title="Delete">
									<i class="far fa-trash-alt"></i>
								</button>&nbsp;
							</td>
						</tr>';
					}
				}
				
				/* List files */
				foreach ( $files as $file ){
					$file_extension  = strtoupper( $file->getExtension() );
					
					if( $file->isDot() || $file->getFilename() == 'advanced-php-file-manager.php' ) continue; /* Ignore dot files and "advanced-php-file-manager.php" file */
					if( $file->isFile() ){
						$response['content'] .= '
						<tr align="right">
							<td align="left">
								<input type="checkbox" class="check-file mr-2 align-middle" id="check-file" data-filename="' . $file->getFilename() . '" />';
								
								if( in_array( $file_extension, $supported_images ) ){
									list( $img_width, $img_height ) = getimagesize( $file->getPathname() );
									$response['content'] .= '<a href="#" class="font-weight-bold text-dark ww ellipsis view-photo" data-width="' . $img_width . '" data-height="' . $img_height . '" data-filename="' . $file->getFilename() . '" data-url="' . SELF_URL . '?action=view-photo&filepath=' . $file->getPathname() . '" data-pathname="' . $file->getPathname() . '" data-path="' . $file->getPath() . '" title="' . $file->getFilename() . '">' . $file->getFilename() . '</a>';
								} else if( ! in_array( $file_extension, $unsupported_for_read_write ) ){
									$response['content'] .= '<a href="#" class="font-weight-bold text-dark ww ellipsis" data-target="#modal" data-toggle="modal" data-backdrop="static" data-keyboard="false" data-action="edit-file" data-filename="' . $file->getFilename() . '" data-pathname="' . $file->getPathname() . '" data-path="' . $file->getPath() . '" data-pathname-striped="' . strip_specials( $file->getPathname() ) . '" title="' . $file->getFilename() . '">' . $file->getFilename() . '</a>';
								} else {
									$response['content'] .= '<span class="font-weight-bold text-dark ww ellipsis" title="' . $file->getFilename() . '">' . $file->getFilename() . '</span>';
								}
								
							$response['content'] .= '	
							</td>
							<td data-sort-value="' . $file->getSize() . '">' . format_bytes( $file->getSize() ) . '</td>
							<td data-sort-value="' . filemtime( $file->getPathname() ) . '">' . date( 'Y/m/d H:i:s', filemtime( $file->getPathname() ) ) . '</td>
							<td>' . decoct( $file->getPerms() & 0777 ) . '</td>
							<td>';						
								if( in_array( $file_extension, $supported_archives ) ){
									$response['content'] .= '
										<button type="button" class="btn btn-sm btn-link decompress-file-btn" data-type="file" data-filename="' . $file->getFilename() . '" data-path="' . $file->getPath() . '" title="Decompress">
											<i class="fas fa-archive"></i>
										</button>&nbsp;
									';
								}
								
								$response['content'] .= '
								<a href="' . SELF_URL . '?action=download&filepath=' . urlencode( $file->getPathname() )  . '" class="btn btn-sm btn-link download-file-btn" title="Download">
									<i class="fa fa-download" aria-hidden="true"></i>
								</a>&nbsp;
								
								<button type="button" class="btn btn-sm btn-link rename-file-btn" data-filename="' . $file->getFilename() . '" data-path="' . $file->getPath() . '" title="Rename">
									<i class="far fa-edit"></i>
								</button>&nbsp;
								
								<button type="button" class="btn btn-sm btn-link delete-file-btn" data-type="file" data-filename="' . $file->getFilename() . '" data-path="' . $file->getPath() . '" data-pathname="' . $file->getPathname() . '" title="Delete">
									<i class="far fa-trash-alt"></i>
								</button>&nbsp;
							</td>
						</tr>';
					}
				}
			
				$response['content'] .= '
					</tbody>
				</table>
			</div>';
			
		}
		
		$response['content'] = compress_output( $response['content'] );
	}
	
	/* Read file */
	if( isset( $_POST['action'] ) && $_POST['action'] == 'read-file' && ine('path') ){
		$response['content'] = htmlentities( read_file( $_POST['path'] ) );
	}
	
	/* Update file */
	if( isset( $_POST['action'] ) && $_POST['action'] == 'update-file' && ine('path') && isset( $_POST['data']['file_name'] ) && isset( $_POST['data']['file_data'] ) ){
		if( update_file( $_POST['path'], $_POST['data']['file_data'] ) ){
			$response['content'] = '<u>' . $_POST['data']['file_name'] . '</u> successfully updated';
		} else {
			$response['status'] = 0;
			$response['content'] = 'Error: File update failed';
		}
	}
	
	/* Rename file */
	if( isset( $_POST['action'] ) && $_POST['action'] == 'rename-file' && ine('path') && 
		isset( $_POST['data']['old_name'] ) && ! empty( $_POST['data']['old_name'] ) && 
		isset( $_POST['data']['new_name'] ) && ! empty( $_POST['data']['new_name'] ) ){
		
		$file_path = $_POST['path'] . DIRECTORY_SEPARATOR;
		$old_file_name = $_POST['data']['old_name'];
		$new_file_name = $_POST['data']['new_name'];
		
		if( rename( $file_path . $old_file_name, $file_path . $new_file_name ) ){
			$response['content'] = 'File successfully renamed';
		} else {
			$response['status'] = 0;
			$response['content'] = 'Error: File renaming failed';
		}
	}
	
	/* Delete file */
	if( isset( $_POST['action'] ) && $_POST['action'] == 'delete-file' && ine('path') ){
		if( delete_file( $_POST['path'] ) ){
			$response['content'] = 'File successfully deleted';
		} else {
			$response['status'] = 0;
			$response['content'] = 'Error: File deletion failed';
		}
	}
	
	/* Create file */
	if( isset( $_POST['action'] ) && $_POST['action'] == 'create-file' && ine('path') ){
		if ( ! file_exists( $_POST['path'] ) ){
			if( $handle = @fopen( $_POST['path'], "w" ) ){
				@fclose( $handle );
			}
			@chmod( $_POST['path'], 0644 );
			$response['content'] = 'File successfully created';
		} else {
			$response['status'] = 0;
			$response['content'] = 'Error: File already exists in this directory';
		}
	}
	
	/* Create folder */
	if( isset( $_POST['action'] ) && $_POST['action'] == 'create-folder' && ine('path') ){
		if ( ! file_exists( $_POST['path'] ) ){
			@mkdir( $_POST['path'], 0755 );
			@chmod( $_POST['path'], 0755 );
			$response['content'] = 'Folder successfully created';
		} else {
			$response['status'] = 0;
			$response['content'] = 'Error: Folder already exists in this directory';
		}
	}
		
	/* Copy File(s) */
	if( isset( $_POST['action'] ) && $_POST['action'] == 'copy' && ine('path') && 
		isset( $_POST['data']['files'] ) && ! empty( $_POST['data']['files'] ) && 
		isset( $_POST['data']['current_path'] ) 
	){
		$current_path = ! empty( $_POST['data']['current_path'] ) ? $_POST['data']['current_path'] : PROJECT_ROOT;
		foreach( $_POST['data']['files'] as $file ){
			$file = trim( $file );
			copy_file( urldecode( $current_path ) . '/' . $file, $_POST['path'] . '/' . $file );
		}
		
		$response['content'] = 'Selected files(s) successfully coppied';
	}
	
	/* Move File(s) */
	if( isset( $_POST['action'] ) && $_POST['action'] == 'move' && ine('path') && 
		isset( $_POST['data']['files'] ) && ! empty( $_POST['data']['files'] ) && 
		isset( $_POST['data']['current_path'] )
	){
		$current_path = ! empty( $_POST['data']['current_path'] ) ? $_POST['data']['current_path'] : PROJECT_ROOT;
		foreach( $_POST['data']['files'] as $file ){
			$file = trim( $file );
			move_file( urldecode( $current_path ) . '/' . $file, $_POST['path'] . '/' . $file );
		}
		
		$response['content'] = 'Selected files(s) successfully moved';
	}
	
	/* Delete File(s) */
	if( isset( $_POST['action'] ) && $_POST['action'] == 'delete' && ine('path') && isset( $_POST['data'] ) && isset( $_POST['data']['files'] ) ){
		foreach( $_POST['data']['files'] as $file ){
			$file_path = $_POST['path'] . '/' . $file;
			if( file_exists( $file_path ) ){
				delete_file( $file_path );
			}
		}
		
		$response['content'] = 'Selected file(s) successfully deleted';
	}
	
	/* Upload File(s) */
	if( isset( $_POST['action'] ) && $_POST['action'] == 'upload' && ine('path') && 
		isset( $_FILES['files'] ) && ! empty( $_FILES['files'] )
	){
		if( isset( $_POST['dropzone'] ) ){
			$file_location = isset( $_POST['file_location'] ) ? rtrim($_POST['file_location'], '/.') : $_FILES['files']['name'];
			$new_path = $_POST['path'] . '/' . $file_location;
			$folder = substr($new_path, 0, strrpos($new_path, '/'));
			
			if( ! is_dir($folder) && $file_location ) {
				$old = umask(0);
				@mkdir( $folder, 0755, true );
				@chmod( $folder, 0755 );
				umask($old);
			}
			
			if( move_uploaded_file($_FILES['files']['tmp_name'], $new_path) ){}
			
		} else {
			foreach ( $_FILES['files']['name'] as $f => $name ){
				if ( $_FILES['files']['error'][$f] == 4 ){
					continue; 
				}
				if ( $_FILES['files']['error'][$f] == 0 ){
					if ( is_dir( $_POST['path'] ) && is_writable( $_POST['path'] ) ) {
						move_uploaded_file( $_FILES['files']['tmp_name'][$f], $_POST['path'] . '/' . $name );
						$response['content'] = 'File(s) successfully uploaded.';
						
					} else {
						$response['status'] = 0;
						$response['content'] = 'Upload directory is not writable, or does not exist';
					}
				}
			}
		}
	}
	
	/* Compress File(s) */
	if( isset( $_POST['action'] ) && $_POST['action'] == 'compress' && ine('path') && isset( $_POST['data'] ) && isset( $_POST['data']['files'] ) && isset( $_POST['data']['name'] ) ){
		$archive_name = $_POST['data']['name'];
		$archive_files = $_POST['data']['files'];
		$archive_path = $_POST['path'];
		
		if( $archive_name ){
			chdir($archive_path);

			$zipper = new archive();
			
			if ( $zipper->create($archive_name, $archive_files) ) {
				$response['content'] = 'Archive ' . $archive_name . ' successfullly created.';
			}
		}
	}
	
	/* Decompress Single File */
	if( isset( $_POST['action'] ) && $_POST['action'] == 'decompress' && ine('path') && 
		isset( $_POST['data']['filename'] ) && ! empty( $_POST['data']['filename'] ) && 
		isset( $_POST['data']['current_path'] ) && ! empty( $_POST['data']['current_path'] ) 
	){
		$destination_path = $_POST['path'] . '/';
		$current_path = $_POST['data']['current_path'] . '/';
		$filename = $_POST['data']['filename'];
		
		if ( file_exists( $current_path . $filename ) ){
			/* Code here */
			unzip_file($filename, $current_path, $destination_path );
			$response['content'] = 'Archive ' . $filename . ' successfullly decompressed.';
		}
	}
	
	echo json_encode( $response );
	exit();
}

ob_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<title><?php echo PRODUCT_NAME; ?> by <?php echo AUTHOR; ?></title>
	
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	
	<link rel="shortcut icon" href="//carlofontanos.com//wp-content/themes/carlo-fontanos/img/advanced-php-file-manager-favicon.ico" type="image/ico" />
	<link rel="stylesheet" href="//stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" />
	<link rel="stylesheet" href="//use.fontawesome.com/releases/v5.2.0/css/all.css" />
	<link rel="stylesheet" href="//cdn.rawgit.com/arboshiki/lobibox/master/dist/css/lobibox.min.css" />
	<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/nprogress/0.2.0/nprogress.min.css" />
	<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/jstree/3.3.4/themes/default/style.min.css" />
	<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/photoswipe/4.1.2/photoswipe.min.css"> 
	<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/photoswipe/4.1.2/default-skin/default-skin.min.css"> 
	
	<script src="//code.jquery.com/jquery-3.3.1.min.js"></script>
	
	<style>
	main { background: #fff; font-size: <?php echo $global_settings->font_size ? $global_settings->font_size : 14; ?>px; min-height: 100vh; }
	pre.code-editor { margin: 0; }
    .dz-drag-hover { opacity: 0.5; outline-color:red; outline-style:dashed;; }
	.ellipsis { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: inline-block; max-width: 300px; vertical-align: middle; }
	.directory-tree { overflow: auto; max-height: 550px; }
	.dropzone-fullscreen { background-color:rgba(0, 0, 0, 0.6); width: 100%; bottom: 0px; top: 0px; left: 0; position: absolute; z-index: 10; }
	.editor-tab-unsaved { box-shadow: 0 2px 0 #dc3838 inset; }
	.dropzone-progress { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 50%; text-align: center; color: #fff; }
	.table-files { min-width: 850px; overflow: auto; }
	.table tbody tr.highlight td, .table tbody tr.highlight td a.open-directory, .table tbody tr.highlight td a.text-dark, .table tbody tr.highlight td span.text-dark { background-color: #3d7bd1; color: #fff !important; }
	.ui-tabs .ui-tabs-nav { margin: 0; padding: 0; border-bottom: 1px solid #dee2e6; }
	.ui-tabs .ui-tabs-nav .ui-tabs-tab { display: inline-block; border-right: 2px solid #fff; background: #dee2e6; }
	.ui-tabs .ui-tabs-nav .ui-tabs-anchor { display: inline-block; padding: 0.2rem 5px 0.2rem 1rem; color: #333; font-size: 14px; font-family: inherit; }
	.ui-tabs .ui-tabs-nav .ui-tabs-anchor .close-tab-file-editor { margin-left: 15px; vertical-align: inherit; }
	.ui-tabs .ui-tabs-nav .ui-tabs-anchor .close-tab-file-editor .fa-times { font-size: 12px; }
	.ui-tabs .ui-tabs-nav .ui-tabs-anchor .close-tab-file-editor .fa-times:hover { color: #c14242 !important; }
	.ui-tabs .ui-tabs-nav .ui-state-hover { background: #d5d9de; }
	.ui-tabs .ui-tabs-nav .ui-tabs-anchor:hover { text-decoration: none; }
	.ui-tabs .ui-tabs-nav .ui-tabs-tab.ui-tabs-active { background: #fff; }
	.modal { padding-right: 0 !important; }
	.modal-dialog { min-width: 100%; margin: 0; } 
	.modal-content { height: auto; min-height: 100%; border-radius: 0; border: 0; }
	.modal-header { padding: 5px 10px; }
	.modal-body { padding: 0; }
	.ww { word-wrap: break-word; } 
	.va-m { vertical-align: middle; } 
	.c-p { cursor: pointer; }
	#nprogress .bar { background: #ff5b63; z-index: 9999; height: 3px; }
	#nprogress .spinner-icon { border-top-color: #ff5b63; border-left-color: #ff5b63; }
	.login-page input, .login-page button { font-size: 1em; }
	.modal .ace-tomorrow-night-blue { background-color: #323b44; }
	.modal .ace-tomorrow-night-blue .ace_gutter { background-color: #323b44; color: #a9a9a9; }
	.modal .ace-tomorrow-night-blue .ace_marker-layer .ace_active-line, .modal .ace-tomorrow-night-blue .ace_gutter-active-line { background: #404c58; } 
	.modal .ace-tomorrow-night-blue .ace_marker-layer .ace_selected-word { border: 1px solid #ffffff; }
	.modal .ace-tomorrow-night-blue .ace_marker-layer .ace_selection { background: #7e868e; }
	
	@media (max-width: 768px){
		.jstree-default-responsive .jstree-anchor { font-weight: normal; text-shadow: none; font-size: 1rem; }
		.directory-tree { min-height: 150px; }
	}
	@media (max-width: 480px){
		.breadcrumb { padding: 3px; }
		.action-btns button, .action-btns form { display: inline-block; margin: 2px 0; width: 49%; }
		.action-btns label { width: 100%; }
		.editable-buttons { display: block; margin-top: 5px; }
	}
	@media (max-width: 360px){
		.action-btns button, .action-btns form { width: 100%; }
	}
	
	main.dark, .dark .bg-flat, .dark .btn-outline-primary, .dark .btn-outline-danger { background: #2d353d; color: #98a6ad; }
	.dark .breadcrumb { background: #323b44; }
	.dark .login-page .form-control, .dark .login-page .btn { background: none; color: #aaa; border: 0; }
	.dark a, .dark .text-dark, .dark .text-dark:hover, .dark .text-dark:focus { color: #98a6ad !important; }
	.dark .card, .dark .modal-content { background: #2d353d; color: #98a6ad; }
	.dark .btn-link { color: #98a6ad; }
	.dark .table td, .dark .table th { border-top: 1px solid #45555d; }
	.dark .table thead th { border-bottom: 0; }
	.dark .btn-outline-primary, .dark .btn-outline-danger { border-color: #45555d; }
	.dark .modal-header { border-bottom: 1px solid #45555d; }
	.dark .modal-body { background-color: #323b44; }
	.dark .jstree-default .jstree-clicked, .dark .jstree-default .jstree-clicked:hover { background: #485563;}
	.dark .modal-header .btn { border-color: #fff; color: #fff; }
	.dark .modal-header .btn:focus, .dark .modal-header .btn:hover { border-color: transparent; }
	.dark .search-container { background: #2d353d; }
	.dark .search-container .input-group-text, .dark .search-container .form-control { background: none; color: #98a6ad; border: 0; }
	.dark #nprogress .bar { background: #fff; z-index: 9999; height: 3px; }
	.dark #nprogress .spinner-icon { border-top-color: #fff; border-left-color: #fff; }
	.dark .ui-tabs .ui-tabs-nav { margin: 0; padding: 0; border-bottom: 1px solid #45555d; }
	.dark .ui-tabs .ui-tabs-nav .ui-tabs-tab { display: inline-block; border-right: 2px solid #27313a; background: #404c58; }
	.dark .ui-tabs .ui-tabs-nav .ui-state-hover { background: #323b44; }
	.dark .ui-tabs .ui-tabs-nav .ui-tabs-tab.ui-tabs-active { background: #323b44; }
	</style>

</head>
<body>
	<main class="<?php echo $global_settings->theme; ?>">
		<div class="dropzone-fullscreen d-none">
			<div class="dropzone-progress">
				<div class="dropzone-status"></div>
				<div class="dropzone-uploading"></div>
			</div>
		</div>
		
		<div class="pswp" tabindex="-1" role="dialog" aria-hidden="true">
			<div class="pswp__bg"></div>
			<div class="pswp__scroll-wrap">
				<div class="pswp__container">
					<div class="pswp__item"></div>
					<div class="pswp__item"></div>
					<div class="pswp__item"></div>
				</div>
				<div class="pswp__ui pswp__ui--hidden">
					<div class="pswp__top-bar">
						<div class="pswp__counter"></div>
						<button class="pswp__button pswp__button--close" title="Close (Esc)"></button>
						<button class="pswp__button pswp__button--share" title="Share"></button>
						<button class="pswp__button pswp__button--fs" title="Toggle fullscreen"></button>
						<button class="pswp__button pswp__button--zoom" title="Zoom in/out"></button>
						<div class="pswp__preloader">
							<div class="pswp__preloader__icn">
							  <div class="pswp__preloader__cut">
								<div class="pswp__preloader__donut"></div>
							  </div>
							</div>
						</div>
					</div>
					<div class="pswp__share-modal pswp__share-modal--hidden pswp__single-tap">
						<div class="pswp__share-tooltip"></div> 
					</div>
					<button class="pswp__button pswp__button--arrow--left" title="Previous (arrow left)"></button>
					<button class="pswp__button pswp__button--arrow--right" title="Next (arrow right)"></button>
					<div class="pswp__caption">
						<div class="pswp__caption__center"></div>
					</div>
				</div>
			</div>
		</div>
		
		<div class="container-fluid pt-3">
			<div class="row">
				<?php if( ! isset( $_SESSION['advanced-php-file-manager']['is_logged_in'] ) ): ?>
					<div class="col-md-4 offset-md-4 mt-5 text-center">
						<div class="card rounded-0 shadow">
							<div class="card-block text-center">
								<h3 class="font-weight-bold mt-4 pl-3 pr-3 text-muted"><?php echo PRODUCT_NAME; ?></h3>
								<p class="font-weight-bold text-info">v2.0</p>
								<?php if( isset( $_GET['login-error'] ) ): ?>
									<div class="alert alert-danger bg-danger text-white rounded-0 border-0" role="alert">
										<strong>Error:</strong> <?php echo $_GET['login-error']; ?>
									</div>
								<?php endif; ?>
								<form method="post" class="login-page text-center">
									<input type="hidden" name="action" value="login" />
									
									<div class="p-5">
										<div class="input-group">
											<div class="input-group-prepend">
												<span class="input-group-text bg-transparent border-0"><i class="fas fa-envelope"></i></span>
											</div>
											<input type="text" name="email" class="form-control form-control-lg border-0" placeholder="Username" required />
										</div>
										<div class="input-group mt-3">
											<div class="input-group-prepend">
												<span class="input-group-text bg-transparent border-0"><i class="fas fa-key"></i></span>
											</div>
											<input type="password" name="password" class="form-control form-control-lg border-0" placeholder="Password" required />
										</div>
									</div>
									<button class=" btn btn-block btn-light pt-3 pb-3 mt-3 rounded-0">Login</button>
								</form>
							</div>
						</div>
					</div>
					
				<?php else: ?>
					<div class="col-md-3 mb-3">
						<div class="mb-3">
							<div class="card border-0">
								<div class="card-block p-2 text-center">
									<h5 class="text-daerng"><?php echo PRODUCT_NAME; ?></h5>
									<small class="d-b font-weight-bold text-info">v2.0</small>
								</div>
							</div>
						</div>
						
						<div class="tree-action-msg"></div>
						
						<div class="card border-0">
							<div class="card-block directory-tree p-3"></div>
						</div>
					</div>
					<div class="col-md-9">
						<div class="card p-0 border-0" id="apfmDropzone">
							<div class="card-block">
								<?php if( isset( $_SESSION['advanced-php-file-manager']['is_logged_in'] ) ): ?>
									<div class="clearfix mb-3">
										<span class="float-left ">
											<a id="btn-full-screen" class="c-p font-weight-bold" role="button"><i class="fa fa-expand" aria-hidden="true"></i> &nbsp; <span class="full-screen-link">Full Screen</span></a>
											<a href="#" class="font-weight-bold ml-3 text-dark btn-open-code-editor d-none"><i class="far fa-file-code"></i> Code Editor</a>
										</span>
										<span class="float-right">
											<a href="#" class="text-success" data-target="#modal" data-toggle="modal" data-backdrop="static" data-keyboard="false" data-action="edit-settings"><i class="fa fa-cog" aria-hidden="true"></i> Settings</a> &nbsp; | &nbsp; 
											Logged in as <strong><?php echo $_SESSION['advanced-php-file-manager']['email']; ?></strong> &nbsp;<a href="<?php echo SELF_URL; ?>?logout=1" class="text-danger">Logout</a></span>
									</div>
								<?php endif; ?>
								
								<div class="main-container"></div>
							</div>
						</div>
						<div class="mt-3 mb-3">
							Copyright &copy; <?php echo date('Y'); ?> <a href="<?php echo AUTHOR_URL; ?>" target="_blank"><?php echo AUTHOR; ?> &nbsp; <?php echo AUTHOR_URL; ?></a><br />
							All rights reserved.
						</div>
					</div>
					
				<?php endif; ?>
			</div>
		</div>
		
		<div class="modal full-screen" id="modal" tabindex="-1" role="dialog" aria-labelledby="modal-label" aria-hidden="true">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<span class="modal-title font-weight-bold" id="modal-label">Digishops FM </span>
						<div>
							<a href="#" class="text-dark pl-3 pr-2" data-dismiss="modal" aria-label="Minimize"><i class="fas fa-minus"></i></a>
						</div>
					</div>
					<div class="modal-body">
						<div id="tabs" class="edit-code-container">
							<ul></ul>
						</div>
						
						<div class="col-sm-6 offset-sm-3 edit-settings-container pt-3 pb-3 d-none">
							<?php if( $global_settings ): ?>
								<form method="post" action="<?php echo SELF_URL; ?>" class="edit-settings-form">
									<input type="hidden" name="action" value="edit-settings">
									<h5>Global Settings</h5>
									<hr />
									<div class="form-group row">
										<label class="col-4 col-form-label">Theme</label>
										<div class="col-8">
											<select class="custom-select form-control" name="theme">
												<option value="light" <?php echo $global_settings->theme == 'light' ? 'selected': ''; ?>>Light</option>
												<option value="dark" <?php echo $global_settings->theme == 'dark' ? 'selected': ''; ?>>Dark</option>
											</select>
										</div>
									</div>
									<div class="form-group row">
										<label class="col-4 col-form-label">Font Size</label>
										<div class="col-8">
											<input type="text" class="form-control" value="<?php echo $global_settings->font_size; ?>" name="font_size" />
											<small>In pixels (px)</small>
										</div>
									</div>
									
									<p class="mt-5 text-danger"><strong>Note:&nbsp;</strong>Changes to the settings bellow requires a page refresh in order to take effect.</p>
									<hr />
									<div class="form-group row">
										<label class="col-4 col-form-label">Editor Theme</label>
										<div class="col-8">
											<select class="custom-select form-control" name="editor_theme">
												<optgroup label="Bright">
													<?php 
													$light_editor_theme = json_decode('{"chrome":"Chrome (default for light theme)","clouds":"Clouds","crimson_editor":"Crimson Editor","dawn":"Dawn","dreamweaver":"Dreamweaver","eclipse":"Eclipse","github":"GitHub","iplastic":"IPlastic","solarized_light":"Solarized Light","textmate":"TextMate","tomorrow":"Tomorrow","xcode":"XCode","kuroir":"Kuroir","katzenmilch":"KatzenMilch","sqlserver":"SQL Server"}');
													foreach( $light_editor_theme as $key => $value ){
														$selected = $global_settings->editor_theme == $key ? 'selected': '';
														echo '<option value="' .  $key . '" ' . $selected . '>' .  $value . '</option>';
													}
													?>
												</optgroup>
												<optgroup label="Dark">
													<?php 
													$dark_editor_theme = json_decode('{"ambiance":"Ambiance","chaos":"Chaos","clouds_midnight":"Clouds Midnight","cobalt":"Cobalt","gruvbox":"Gruvbox","gob":"Green on Black","idle_fingers":"idle Fingers","kr_theme":"krTheme","merbivore":"Merbivore","merbivore_soft":"Merbivore Soft","mono_industrial":"Mono Industrial","monokai":"Monokai","pastel_on_dark":"Pastel on dark","solarized_dark":"Solarized Dark","terminal":"Terminal","tomorrow_night":"Tomorrow Night","tomorrow_night_blue":"Tomorrow Night Grey (default for dark theme)","tomorrow_night_bright":"Tomorrow Night Bright","tomorrow_night_eighties":"Tomorrow Night 80s","twilight":"Twilight","vibrant_ink":"Vibrant Ink"}');
													foreach( $dark_editor_theme as $key => $value ){
														$selected = $global_settings->editor_theme == $key ? 'selected': '';
														echo '<option value="' .  $key . '" ' . $selected . '>' .  $value . '</option>';
													}
													?>
													
												</optgroup>
											</select>
											<small><a href="https://ace.c9.io/build/kitchen-sink.html" target="_blank">Preview available here</a></small>
										</div>
									</div>
									<div class="form-group row">
										<label class="col-4 col-form-label">Editor Font Size</label>
										<div class="col-8">
											<input type="text" class="form-control" value="<?php echo $global_settings->editor_font_size; ?>" name="editor_font_size" />
											<small>In pixels (px)</small>
										</div>
									</div>
									<div class="form-group row">
										<label class="col-4 col-form-label">Enable Superuser</label>
										<div class="col-8">
											<select class="custom-select form-control" name="enable_superuser">
												<option value="1" <?php echo $global_settings->enable_superuser == 1 ? 'selected': ''; ?>>Yes</option>
												<option value="0" <?php echo $global_settings->enable_superuser == 0 ? 'selected': ''; ?>>No</option>
											</select>
											<small>Allows you to access the root directory of your server. Use this option at your own risk.</small>
										</div>
									</div>
									
									<br />
									<h5>Login Details</h5>
									<hr />
									<div class="form-group row">
										<label class="col-4 col-form-label">Username</label>
										<div class="col-8">
											<input type="text" name="login_email" class="form-control" value="<?php echo $global_settings->login_email; ?>" />
										</div>
									</div>
									<div class="form-group row">
										<label class="col-4 col-form-label">Old Password</label>
										<div class="col-8">
											<input type="password" name="old_password" class="form-control" />
										</div>
									</div>
									<div class="form-group row">
										<label class="col-4 col-form-label">New Password</label>
										<div class="col-8">
											<input type="password" name="new_password" class="form-control" />
										</div>
									</div>
									<div class="form-group row">
										<label class="col-4 col-form-label">Repeat New Password</label>
										<div class="col-8">
											<input type="password" name="repeat_new_password" class="form-control" />
											<input type="submit" class="btn btn-outline-success mt-3" value="Save Changes" />
										</div>
									</div>
								</form>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</main>
	
	<?php if( isset( $_SESSION['advanced-php-file-manager']['is_logged_in'] ) ): ?>
	<script src="//cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js"></script>
	<script src="//stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js"></script>
	<script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
	<script src="//cdnjs.cloudflare.com/ajax/libs/ace/1.3.2/ace.js" charset="utf-8"></script>
	<script src="//cdn.rawgit.com/arboshiki/lobibox/master/dist/js/lobibox.min.js"></script>
	<script src="//cdnjs.cloudflare.com/ajax/libs/nprogress/0.2.0/nprogress.min.js"></script>
	<script src="//cdnjs.cloudflare.com/ajax/libs/jquery.form/4.2.2/jquery.form.min.js"></script>
	<script src="//cdnjs.cloudflare.com/ajax/libs/history.js/1.8/bundled-uncompressed/html5/jquery.history.js"></script>
	<script src="//cdnjs.cloudflare.com/ajax/libs/jstree/3.3.4/jstree.min.js"></script>
	<script src="//cdnjs.cloudflare.com/ajax/libs/stupidtable/1.1.3/stupidtable.min.js"></script>
	<script src="//cdnjs.cloudflare.com/ajax/libs/dropzone/5.5.1/min/dropzone.min.js"></script>
	<script src="//cdnjs.cloudflare.com/ajax/libs/photoswipe/4.1.2/photoswipe.min.js" defer></script> 
	<script src="//cdnjs.cloudflare.com/ajax/libs/photoswipe/4.1.2/photoswipe-ui-default.min.js" defer></script>
	<script type="text/javascript">
	var tree_loaded = false;
	var tree_nodes_array = <?php echo TREE_NODES_ARRAY; ?>;
	var tree_current_count = 0;
	
	function tree_auto_load(){
		if (tree_current_count > tree_nodes_array.length) return;
		
		var node_id = tree_nodes_array.slice(0, tree_current_count + 1).join('/');
		var node = $('.directory-tree').find("[id='" + node_id + "']:eq(0)");
		
		tree_current_count++;
		if (tree_current_count == tree_nodes_array.length){
			if (node.length){
				$('.directory-tree').jstree(true).open_node(node, function(){
					$('.directory-tree').jstree(true).select_node(node,true); /* Highlight folder */
					tree_loaded = true;
				}, false);
			} else {
				tree_loaded = true;
			}
		} else {
			if (node.length){
				$('.directory-tree').jstree(true).open_node(node, tree_auto_load, false);
			} else {
				tree_auto_load();
			}
		}
	}
	
	function ajax(action, path, callback, other_data){
		NProgress.start();
		$.ajax({
			type: 'post',
			url: window.location.href.split('?')[0],
			data: {'action': action, 'path': path, 'data': other_data},
			success: function(response){
				response = JSON.parse(response);
				callback(response);
				NProgress.done();
			}
		});
	}
	
	function list_directory(path){
		ajax('list-directory', path, function(response){
			if(response.status == 1){
				$('.main-container').html(response.content);
			} else if(response.status == 0){
				$('.main-container').html(response.content);
			}
			$('.table-files').stupidtable();
		});
	}
	
	function notify(type, msg, speed, delayIndicator){
		Lobibox.notify(type, {
			size: 'mini',
			sound: false,
			icon: false,
			delay: speed ? speed : 2000,
			showClass: '',
			hideClass: '',
			position: 'bottom right',
			msg: msg,
			delayIndicator: delayIndicator ? true: false
		});
	}
	
	function scroll_to(element){
		jQuery('html, body').animate({scrollTop: jQuery(element).offset().top-100}, 150);
	}
	
	function get_url_value(variable){
       var query = window.location.search.substring(1);
       var vars = query.split("&");
       for (var i=0;i<vars.length;i++){
               var pair = vars[i].split("=");
               if(pair[0] == variable){return pair[1];}
       }
       return '';
    }
	
	function toggle_full_screen(elem) {
		elem = elem || document.documentElement;
		if (!document.fullscreenElement && !document.mozFullScreenElement &&
			!document.webkitFullscreenElement && !document.msFullscreenElement) {
			if (elem.requestFullscreen) {
				elem.requestFullscreen();
			} else if (elem.msRequestFullscreen) {
				elem.msRequestFullscreen();
			} else if (elem.mozRequestFullScreen) {
				elem.mozRequestFullScreen();
			} else if (elem.webkitRequestFullscreen) {
				elem.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT);
			}
		} else {
			if (document.exitFullscreen) {
				document.exitFullscreen();
			} else if (document.msExitFullscreen) {
				document.msExitFullscreen();
			} else if (document.mozCancelFullScreen) {
				document.mozCancelFullScreen();
			} else if (document.webkitExitFullscreen) {
				document.webkitExitFullscreen();
			}
		}
	}
	
	function focus_editor(editor_id){
		var editor = ace.edit(editor_id);
			editor.focus();
	}
	
	function close_tab_file_editor(element){
		var tab_id = element.remove().attr('aria-controls');
		var editor = ace.edit(tab_id);
			editor.destroy();
			editor.container.remove();
			
		$('#' + tab_id).remove();
		$('.edit-code-container').tabs('refresh');
		
		if($('.edit-code-container li').length == 0){
			$('#modal').modal('hide');
			$('.btn-open-code-editor').addClass('d-none');
		}
	}
	
	$.fn.extend({
		toggleText: function(a, b){
			return this.text(this.text() == b ? a : b);
		}
	});
	
	$.fn.add_editor_tab = function(file_pathname_striped, file_pathname, file_path, tab_name, contents){
		if($('#tab-' + file_pathname_striped).length > 0){
			$(this).tabs({
				active: $('a[href="#tab-' + file_pathname_striped + '"]').parent().index(), /* Set existing tab as active */
				activate: function(event, ui){
					focus_editor('editor' + ui.newTab.attr('aria-controls').slice(3)); /* Focus cursor on clicked tab */
				}
			});
			focus_editor('editor-' + file_pathname_striped); /* Focus cursor on existing tab */
		} else {
			$(this).append(
				'<div id="tab-' + file_pathname_striped + '">' + 
					'<pre id="editor-' + file_pathname_striped + '" class="code-editor">' + contents + '</pre>' + 
				'</div>'
			);
			$('ul', this).append(
				'<li>' + 
					'<a href="#tab-' + file_pathname_striped + '" title="' + file_pathname + '">' + 
						tab_name + 
						'<button class="btn btn-link p-0 close-tab-file-editor" title="Close">' + 
							'<i class="fas fa-times text-dark"></i>' + 
						'</button>' + 
					'</a>' + 
				'</li>'
			);
			$(this).tabs('refresh');
			$(this).tabs({ 
				active: -1, /* Set last tab as active */
				activate: function(event, ui){
					focus_editor('editor' + ui.newTab.attr('aria-controls').slice(3)); /* Focus cursor on clicked tab */
				}
			});
			
			var editor = ace.edit('editor-' + file_pathname_striped);
				editor.setTheme('ace/theme/<?php echo $global_settings->editor_theme; ?>');
				editor.getSession().setMode('ace/mode/javascript');
				editor.session.setUseWorker(false);
				editor.focus();
				editor.commands.addCommand({
					name: 'saveFile',
					bindKey: {
						win: 'Ctrl-S',
						mac: 'Command-S',
						sender: 'editor|cli'
					},
					exec: function(env, args, request){
						ajax('update-file', file_pathname, function(response){
							if(response.status == 1){
								editor.session.getUndoManager().markClean();
								list_directory(file_path);
								notify('success', response.content, 300, false);
								var status = editor.session.getUndoManager().isClean();
								if(status){
									$('.ui-tabs-active').removeClass('editor-tab-unsaved');
								} else {
									$('.ui-tabs-active').addClass('editor-tab-unsaved');
								}
							} else if(response.status == 0){
								notify('error', response.content);
							}
						}, {file_data: editor.getValue(), file_name: tab_name} );
					}
				});
				editor.on('input', function() {
					var status = editor.session.getUndoManager().isClean();
					if(status){
						$('.ui-tabs-active').removeClass('editor-tab-unsaved');
					} else {
						$('.ui-tabs-active').addClass('editor-tab-unsaved');
					}
				});
		}
		
		$('.code-editor').css('height', $(window).height() - 66);
		document.getElementById('editor-' + file_pathname_striped).style.fontSize = '<?php echo $global_settings->editor_font_size; ?>px';
	};
	
	jQuery(document).ready(function($){
		var History = window.History;
	
		if (History.enabled){
			var current_path = get_url_value('path');
			var path = current_path ? current_path : '';
			list_directory(path);
		} else {
			return false;
		}
		
		History.Adapter.bind(window, 'statechange', function(){
			var State = History.getState(); 
			list_directory(State.data.path);
			History.log(State);
		});
		
		
		
		/* Dropzone */
		var apfmDropzone_counter = 1;
		Dropzone.options.apfmDropzone = {
			init: function(){
				this.on('sending', function(file, xhr, data){
					$('.dropzone-fullscreen').removeClass('d-none');
					
					/* Include full path of the file when posting to the server. */
					if(file.fullPath){
						data.append('file_location', file.fullPath);
					}
					data.append('path', $('.refresh-path-btn').attr('data-path'));
					data.append('dropzone', 1);
					data.append('action', 'upload');
				});
				this.on('complete', function (file){
					/* When all uploads completed ... */
					if (this.getUploadingFiles().length === 0 && this.getQueuedFiles().length === 0){
						this.removeAllFiles(true);
						$('.dropzone-fullscreen').addClass('d-none');
						list_directory($('.refresh-path-btn').attr('data-path'));
						apfmDropzone_counter = 1;
					}
					
					$('.dropzone-status').html(
						'<p>Uploading files, please wait</p>' + 
						'<div class="progress">' + 
							'<div ' + 
								'class="progress-bar progress-bar-striped progress-bar-animated bg-success" ' + 
								'style="width: ' + (apfmDropzone_counter / this.getAcceptedFiles().length) * 100  + '%' + 
							'">' + 
								apfmDropzone_counter + ' / ' + this.getAcceptedFiles().length + 
							'</div>' + 
						'</div>'
					);
					$('.dropzone-uploading').html('<p class="m-t">Currently Uploading:&nbsp;' + file.fullPath);
					apfmDropzone_counter++;
				});
			}
		};
		$('#apfmDropzone').dropzone({
			paramName: 'files',
			url: window.location.href.split('?')[0],
			previewsContainer: false,
			clickable: false
		});
		
		document.getElementById('btn-full-screen').addEventListener('click', function() {
			toggle_full_screen();
			$('.full-screen-link').toggleText('Full Screen', 'Exit Full Screen');
		});
		
		$('body').on('click', '.open-directory', function(e){
			e.preventDefault();
			
			/* Detect and handle if there is a pending "copy" or "move" action */
			var action = $('.directory-tree').attr('data-action');
				current_path_from_url = get_url_value('path');
				current_path = current_path_from_url ? current_path_from_url : '<?php echo str_replace('\\', '/', PROJECT_ROOT); ?>';
				self = $(this);
				
			if(action == 'copy' || action == 'move'){
				var action_verb = action == 'copy' ? 'copying' : 'moving';
				if(!confirm('Are you sure you want to proceed with ' + action_verb + ' the selected file(s)?')){
					return false;
				}
				
				var path = self.attr('href');
					files = $('.check-file:checkbox:checked');
					files_array = [];
					data = {};
					
				for (var i=0, item; item = files[i]; i++){
					files_array.push($(item).data('filename'));
				}
				
				data.current_path = current_path;
				data.files = files_array;
				if(files_array.length){
					ajax(action, path, function(response){
						if(response.status == 1){
							/* What to do after successfull "move" or "copy" of file(s) */
							notify('success', response.content);
							$('.tree-action-msg').html('');
							$('.directory-tree').removeAttr('data-action');
							self.removeClass('jstree-clicked');
							list_directory(current_path);
							$('.directory-tree').jstree('refresh');
						} else if(response.status == 0){
							notify('error', response.content);
						}
					}, data );
				}
				
			} else if(action == 'decompress'){
				if(!confirm('Are you sure you want to proceed with decompressing the selected zip file into the target destination?')){
					return false;
				}
				
				ajax('decompress', self.attr('href'), function(response){
					if(response.status == 1){
						/* What to do after successfull "decompress" of zip file */
						notify('success', response.content);
						$('.tree-action-msg').html('');
						$('.directory-tree').removeAttr('data-action');
						self.removeClass('jstree-clicked');
						list_directory(current_path);
						$('.directory-tree').jstree('refresh');
					} else if(response.status == 0){
						notify('error', response.content);
					}
				}, {filename: $('.directory-tree').attr('data-filename'), current_path: current_path});
				
			} else {
				/* Proceed with normal opening of directory */
				History.pushState(
					{path: self.attr('href')}, 
					document.title, 
					window.location.href.split('?')[0] + '?path=' + self.attr('href')
				);
				scroll_to('.main-container');
			}
		});
		
		$('body').on('click', '.delete-file-btn', function(e){
			e.preventDefault();
			if(confirm('Are you sure you want to delete the ' + $(this).data('type') + ' "' + $(this).data('filename') + '" ?')){
				var self = $(this);
				ajax('delete-file', self.data('pathname'), function(response){
					if(response.status == 1){
						list_directory(self.data('path'));
						notify('success', response.content);
						if(self.data('type') == 'folder'){
							$('.directory-tree').jstree('refresh');
						}
					} else if(response.status == 0){
						notify('error', response.content);
					}
				});
			}
		});
		
		$('body').on('click', '.rename-file-btn', function(e){
			var path = $(this).data('path');
				old_name = $(this).data('filename');
				new_name = prompt('Enter new name', old_name);
				data = {'old_name': old_name, 'new_name': new_name};
				
			if (new_name != null){
				ajax('rename-file', path, function(response){
					if(response.status == 1){
						list_directory(path);
						notify('success', response.content);
					} else if(response.status == 0){
						notify('error', response.content);
					}
				}, data );
			}
		});
		
		$('body').on('click', '.decompress-file-btn', function(e){
			e.preventDefault();
			$('.tree-action-msg').html(
				'<div class="alert bg-primary text-white" role="alert">' + 
					'<h5>Decompress file is enabled</h5>' + 
					'<p>Select which folder bellow you want to <strong>decompress</strong> the contents of the zip file.</p>' + 
					'<button class="cancel-move-copy-btn btn btn-danger btn-sm">Cancel</button>' + 
				'</div>'
			);
			$('.directory-tree').attr('data-action', 'decompress');
			$('.directory-tree').attr('data-filename', $(this).data('filename'));
			scroll_to('.tree-action-msg');
		});
		
		$('body').on('click', '.select-all-btn', function(e){
			e.preventDefault();
			$('.check-file').prop("checked", true).parents('tr').addClass('highlight');
		});
		$('body').on('click', '.select-none-btn', function(e){
			e.preventDefault();
			$('.check-file').prop("checked", false).parents('tr').removeClass('highlight');
		});
		$('body').on('click', '.check-file', function(e){
			if($(this).parents('tr').hasClass('highlight')){
				$(this).parents('tr').removeClass('highlight');
				$(this).prop("checked", false);
			} else {
				$(this).parents('tr').addClass('highlight');
				$(this).prop("checked", true);
			}
		});
		
		$('body').on('click', '.create-btn', function(e){
			e.preventDefault();
			var path = $(this).data('path');
				action = $(this).data('action');
				file_name = action == 'create-file' ? prompt('Enter the file name', 'new-file.php') : prompt('Enter the folder name', 'New Folder');
			
			if (file_name != null){
				ajax(action, path  + '/' + file_name, function(response){
					if(response.status == 1){
						list_directory(path);
						notify('success', response.content);
						if(action == 'create-folder'){
							$('.directory-tree').jstree('refresh');
						}
					} else if(response.status == 0){
						notify('error', response.content);
					}
				});
			}
		});
		
		$('body').on('click', '.cancel-move-copy-btn', function(e){
			$('.tree-action-msg').html('');
			$('.directory-tree').removeAttr('data-action');
			$('.directory-tree').removeAttr('data-filename');
		});
		
		$('body').on('click', '.mutliple-btn', function(e){
			var path = $(this).data('path');
				action = $(this).data('action');
				files = $('.check-file:checkbox:checked');
				files_array = [];
				proceed = true;
				data = {};
			
			for (var i=0, item; item = files[i]; i++){
				files_array.push($(item).data('filename'));
			}
			
			if(!files.length){
				notify('error', 'No files selected');
				proceed = false;
			}
			
			if(proceed && action == 'compress'){
				var archive_name = prompt('Enter archive name including extension (.zip)', 'myarchive.zip');
				data.name = archive_name;
				
				if(!archive_name && archive_name != null){
					proceed = false;
					notify('error', 'Please enter a name for your archive');
				} else if(archive_name == null){
					proceed = false;
				}
			}
			
			if(proceed && (action == 'copy' || action == 'move')){
				var action_verb = action == 'copy' ? 'Copying' : 'Moving';
				$('.tree-action-msg').html(
					'<div class="alert bg-primary text-white" role="alert">' + 
						'<h5>' + action_verb + ' file(s) is enabled</h5>' + 
						'<p>Select which folder bellow you want to <strong>' + action + '</strong> the selected file(s)</p>' + 
						'<button class="cancel-move-copy-btn btn btn-danger btn-sm">Cancel</button>' + 
					'</div>'
				);
				$('.directory-tree').attr('data-action', action);
				scroll_to('.tree-action-msg');
				proceed = false;
			}
			
			if(proceed && action == 'delete'){
				if(!confirm('Are you sure you want to delete the selected file(s)?')){
					proceed = false;
				}
			}
			
			if(proceed){
				data.files = files_array;
				if(files_array.length){
					ajax(action, path, function(response){
						if(response.status == 1){
							list_directory(path);
							notify('success', response.content);
							$('.directory-tree').jstree('refresh');
						} else if(response.status == 0){
							notify('error', response.content);
						}
					}, data );
				}
			}
		});
		
		$('body').on('click', '.refresh-path-btn', function(e){
			e.preventDefault();
			list_directory($(this).data('path'));
		});
		
		$('body').on('change', '.upload-files-btn input.images-data', function(){
			$(this).parents('form.upload-files-btn').submit();
		});
		$('body').on('submit', 'form.upload-files-btn', function(e){
			e.preventDefault();
			$(this).ajaxSubmit({
				success: function(response, textStatus, xhr, form){
					var response = JSON.parse(response);
					if(response.status == 1){
						notify('success', response.content);
					} else {
						notify('error', response.content);
					}
					$('.upload-files-btn input.images-data').val('');
					list_directory($(form).find('input[name="path"]').val());
				}
			});
		});
		
		$('body').on('click', '.download-file-btn', function(e){
			e.preventDefault();
			window.location = $(this).attr('href');
		});
		
		$('body').on('submit', 'form.edit-settings-form', function(e){
			e.preventDefault();
			$(this).ajaxSubmit({
				success: function(response, textStatus, xhr, form){
					var response = JSON.parse(response);
					if(response.status == 1){
						$('main').attr('class', $('.edit-settings-form [name="theme"]').val());
						$('main').attr('style', 'font-size: ' + $('.edit-settings-form [name="font_size"]').val() + 'px');
						notify('success', response.content);
					} else {
						notify('error', response.content);
					}
				}
			});
		});
		
		$('body').on('click', '.close-tab-file-editor', function(){
			var tab_li = $(this).closest('li');
			if(tab_li.hasClass('editor-tab-unsaved')){
				if(confirm('Closing this file will disregard all unsaved changes. Continue?')){
					close_tab_file_editor(tab_li);
				}
			} else {
				close_tab_file_editor(tab_li);
			}
		});
		
		$('#modal').on('shown.bs.modal', function(e){
			var attr = $(e.relatedTarget);
			if(attr.data('action') == 'edit-file'){
				$('.edit-code-container').removeClass('d-none');
				ajax('read-file', attr.data('pathname'), function(response){
					var tabs = $(".edit-code-container").tabs();
					tabs.add_editor_tab(attr.data('pathname-striped'), attr.data('pathname'), attr.data('path'), attr.data('filename'), response.content);
					$('.btn-open-code-editor').addClass('d-none');
				});
			}
			if(attr.data('action') == 'edit-settings'){
				$('.edit-settings-container').removeClass('d-none');
			}
		});
		
		$('#modal').on('hidden.bs.modal', function(e){
			$('.edit-settings-container').addClass('d-none');
			$('.edit-code-container').addClass('d-none');
			if($('.edit-code-container li').length){
				$('.btn-open-code-editor').removeClass('d-none');
			}
		});
		
		$('body').on('click', '.btn-open-code-editor', function(e){
			e.preventDefault();
			$('#modal').modal('show');
			$('.edit-code-container').removeClass('d-none');
		});
		
		/* Directory Tree */
		$('.directory-tree').jstree({
			'core': {
				'data': {
					'type': 'POST',
					'url': window.location.href.split('?')[0],
					'data': function(node){
						return { 
							'id': node.id,
							'action': 'tree',
							'method': 'list-directory',
						};
					}
				}
			}
		}).on('select_node.jstree', function (e, data){
			if(!tree_loaded) return;
		}).on('loaded.jstree', function (e, data){
			tree_auto_load();
		});
		
		/* Search table */
		$('body').on('keyup', '.search-input', function() {
			var value = $(this).val().toLowerCase();
			$('.search-table tr td:first-child').not( $('#is-header') ).filter(function() {
				$(this).parent('tr').toggle($(this).text().toLowerCase().indexOf(value) > -1)
			});
		});
		
		$('body').on('dragstart', 'a', function(e) { e.preventDefault(); });
		
		$('body').on('click', '.view-photo', function(e){
			e.preventDefault();
			var width = $(this).data('width');
			var height = $(this).data('height');
			var url = $(this).data('url');
			var gallery = new PhotoSwipe(
				document.querySelectorAll('.pswp')[0], 
				PhotoSwipeUI_Default, 
				[{src: url, w: width, h: height}], 
				{ index: 0, closeOnScroll: false, clickToCloseNonZoomable: false, shareEl: false }
			);
			gallery.init();
		});
	});
	</script>
	<?php endif; ?>
</body>
</html>
<?php echo compress_output( ob_get_clean() ); ?>