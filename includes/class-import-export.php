<?php
/**
 * UsersWP Notice display functions.
 *
 * All UsersWP notice display related functions can be found here.
 *
 * @since      1.0.0
 * @author     GeoDirectory Team <info@wpgeodirectory.com>
 */
class UsersWP_Import_Export {
    private $wp_filesystem;
    private $export_dir;
    private $export_url;
    public $per_page;
    public $meta_table_name;
    public $path;
    public $total_rows;
    public $imp_step;

    public function __construct() {
        global $wp_filesystem;

        if ( empty( $wp_filesystem ) ) {
            require_once( ABSPATH . '/wp-admin/includes/file.php' );
            WP_Filesystem();
            global $wp_filesystem;
        }

        $this->wp_filesystem    = $wp_filesystem;
        $this->export_dir       = $this->export_location();
        $this->export_url       = $this->export_location( true );
        $this->per_page         = 20;
        $this->meta_table_name  = uwp_get_table_prefix() . 'uwp_usermeta';
        $this->path  = '';

        add_action( 'userswp_settings_import-export_tab_content', array($this, 'get_ie_content') );
        add_action( 'admin_init', array($this, 'uwp_process_settings_export') );
        add_action( 'admin_init', array($this, 'uwp_process_settings_import') );
        add_action( 'wp_ajax_uwp_ajax_export_users', array( $this, 'uwp_process_users_export' ) );
        add_action( 'wp_ajax_uwp_ajax_import_users', array( $this, 'uwp_process_users_import' ) );
        add_action( 'wp_ajax_uwp_ie_upload_file', array( $this, 'uwp_ie_upload_file' ) );
        add_action( 'wp_ajax_nopriv_uwp_ie_upload_file', array( $this, 'uwp_ie_upload_file' ) );
        add_action( 'admin_notices', array($this, 'uwp_ie_admin_notice') );
        add_filter( 'uwp_get_export_users_status', array( $this, 'uwp_get_export_users_status' ) );
        add_filter( 'uwp_get_import_users_status', array( $this, 'uwp_get_import_users_status' ) );
     }

    public function get_ie_content() {
        $subtab = 'ie-users';

        if (isset($_GET['subtab'])) {
            $subtab = $_GET['subtab'];
        }
        ?>
        <div class="item-list-sub-tabs">
            <ul class="item-list-sub-tabs-ul">
                <li class="<?php if ($subtab == 'ie-users') { echo "current selected"; } ?>">
                    <a href="<?php echo add_query_arg(array('tab' => 'import-export', 'subtab' => 'ie-users')); ?>"><?php echo __( 'Users', 'userswp' ); ?></a>
                </li>
                <li class="<?php if ($subtab == 'ie-settings') { echo "current selected"; } ?>">
                    <a href="<?php echo add_query_arg(array('tab' => 'import-export', 'subtab' => 'ie-settings')); ?>"><?php echo __( 'Settings', 'userswp' ); ?></a>
                </li>
            </ul>
        </div>
        <?php
        if ($subtab == 'ie-users') {
            include_once( USERSWP_PATH . '/admin/settings/admin-settings-ie-users.php' );
        } elseif ($subtab == 'ie-settings') {
            include_once( USERSWP_PATH . '/admin/settings/admin-settings-ie-settings.php' );
        }
    }

    public function export_location( $relative = false ) {
        $upload_dir         = wp_upload_dir();
        $export_location    = $relative ? trailingslashit( $upload_dir['baseurl'] ) . 'cache' : trailingslashit( $upload_dir['basedir'] ) . 'cache';
        $export_location    = apply_filters( 'uwp_export_location', $export_location, $relative );

        return trailingslashit( $export_location );
    }

    public function uwp_ie_admin_notice(){
        if('success' == $_GET['imp-msg']){
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e( 'Settings imported successfully!', 'userswp' ); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Process a settings export that generates a .json file of the settings
     */
    public function uwp_process_settings_export() {
        if( empty( $_POST['uwp_ie_action'] ) || 'export_settings' != $_POST['uwp_ie_action'] )
            return;
        if( ! wp_verify_nonce( $_POST['uwp_export_nonce'], 'uwp_export_nonce' ) )
            return;
        if( ! current_user_can( 'manage_options' ) )
            return;
        $settings = get_option( 'uwp_settings' );
        ignore_user_abort( true );
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=uwp-settings-export-' . date( 'm-d-Y' ) . '.json' );
        header( "Expires: 0" );
        echo json_encode( $settings );
        exit;
    }

    /**
     * Process a settings import from a json file
     */
    public function uwp_process_settings_import() {
        if( empty( $_POST['uwp_ie_action'] ) || 'import_settings' != $_POST['uwp_ie_action'] )
            return;
        if( ! wp_verify_nonce( $_POST['uwp_import_nonce'], 'uwp_import_nonce' ) )
            return;
        if( ! current_user_can( 'manage_options' ) )
            return;
        $extension = end( explode( '.', $_FILES['import_file']['name'] ) );
        if( $extension != 'json' ) {
            wp_die( sprintf(__( 'Please upload a valid .json file. %sGo Back%s' ), '<a href="'.admin_url( 'admin.php?page=userswp&tab=import-export&subtab=ie-settings' ).'">', '</a>' ));
        }
        $import_file = $_FILES['import_file']['tmp_name'];
        if( empty( $import_file ) ) {
            wp_die( sprintf(__( 'Please upload a file to import. %sGo Back%s' ), '<a href="'.admin_url( 'admin.php?page=userswp&tab=import-export&subtab=ie-settings' ).'">', '</a>' ));
        }
        // Retrieve the settings from the file and convert the json object to an array.
        $settings = (array) json_decode( file_get_contents( $import_file ), true );
        update_option( 'uwp_settings', $settings );
        wp_safe_redirect( admin_url( 'admin.php?page=userswp&tab=import-export&subtab=ie-settings&imp-msg=success' ) ); exit;
    }

    public function uwp_process_users_export(){

        $response               = array();
        $response['success']    = false;
        $response['msg']        = __( 'Invalid export request found.', 'userswp' );

        if ( empty( $_POST['data'] ) || !current_user_can( 'manage_options' ) ) {
            wp_send_json( $response );
        }

        parse_str( $_POST['data'], $data );

        $data['step']   = !empty( $_POST['step'] ) ? absint( $_POST['step'] ) : 1;

        $_REQUEST = (array)$data;
        if ( !( !empty( $_REQUEST['uwp_export_users_nonce'] ) && wp_verify_nonce( $_REQUEST['uwp_export_users_nonce'], 'uwp_export_users_nonce' ) ) ) {
            $response['msg']    = __( 'Security check failed.', 'userswp' );
            wp_send_json( $response );
        }

        if ( ( $error = $this->check_export_location() ) !== true ) {
            $response['msg'] = __( 'Filesystem ERROR: ' . $error, 'userswp' );
            wp_send_json( $response );
        }

        $this->set_export_params( $_REQUEST );

        $return = $this->process_export_step();
        $done   = $this->get_export_status();

        if ( $return ) {
            $this->step += 1;

            $response['success']    = true;
            $response['msg']        = '';

            if ( $done >= 100 ) {
                $this->step     = 'done';
                $new_filename   = 'uwp-users-export-' . date( 'y-m-d-H-i' ) . '.csv';
                $new_file       = $this->export_dir . $new_filename;

                if ( file_exists( $this->file ) ) {
                    $this->wp_filesystem->move( $this->file, $new_file, true );
                }

                if ( file_exists( $new_file ) ) {
                    $response['data']['file'] = array( 'u' => $this->export_url . $new_filename, 's' => size_format( filesize( $new_file ), 2 ) );
                }
            }

            $response['data']['step']   = $this->step;
            $response['data']['done']   = $done;
        } else {
            $response['msg']    = __( 'No data found for export.', 'userswp' );
        }

        wp_send_json( $response );

    }

    public function set_export_params( $request ) {
        $this->empty    = false;
        $this->step     = !empty( $request['step'] ) ? absint( $request['step'] ) : 1;
        $this->filename = 'uwp-users-export-temp.csv';
        $this->file     = $this->export_dir . $this->filename;

        do_action( 'uwp_export_users_set_params', $request );
    }

    public function check_export_location() {
        try {
            if ( empty( $this->wp_filesystem ) ) {
                return __( 'Filesystem ERROR: Could not access filesystem.', 'userswp' );
            }

            if ( is_wp_error( $this->wp_filesystem ) ) {
                return __( 'Filesystem ERROR: ' . $this->wp_filesystem->get_error_message(), 'userswp' );
            }

            $is_dir         = $this->wp_filesystem->is_dir( $this->export_dir );
            $is_writeable   = $is_dir && is_writeable( $this->export_dir );

            if ( $is_dir && $is_writeable ) {
                return true;
            } else if ( $is_dir && !$is_writeable ) {
                if ( !$this->wp_filesystem->chmod( $this->export_dir, FS_CHMOD_DIR ) ) {
                    return wp_sprintf( __( 'Filesystem ERROR: Export location %s is not writable, check your file permissions.', 'userswp' ), $this->export_dir );
                }

                return true;
            } else {
                if ( !$this->wp_filesystem->mkdir( $this->export_dir, FS_CHMOD_DIR ) ) {
                    return wp_sprintf( __( 'Filesystem ERROR: Could not create directory %s. This is usually due to inconsistent file permissions.', 'userswp' ), $this->export_dir );
                }

                return true;
            }
        } catch ( Exception $e ) {
            return $e->getMessage();
        }
    }

    public function process_export_step() {
        if ( $this->step < 2 ) {
            @unlink( $this->file );
            $this->print_columns();
        }

        $return = $this->print_rows();

        if ( $return ) {
            return true;
        } else {
            return false;
        }
    }

    public function print_columns() {
        $column_data    = '';
        $columns        = $this->get_columns();
        $i              = 1;
        foreach( $columns as $key => $column ) {
            $column_data .= '"' . addslashes( $column ) . '"';
            $column_data .= $i == count( $columns ) ? '' : ',';
            $i++;
        }
        $column_data .= "\r\n";

        $this->attach_export_data( $column_data );

        return $column_data;
    }

    public function get_columns() {
        global $wpdb;
        $columns = array();

        foreach ( $wpdb->get_col( "DESC " . $this->meta_table_name, 0 ) as $column_name ) {
            $columns[] = $column_name;
        }

        return apply_filters( 'uwp_export_users_get_columns', $columns );
    }

    public function get_export_data() {
        global $wpdb;
        $data = $wpdb->get_results( "SELECT * FROM $this->meta_table_name WHERE 1=1 LIMIT 0,". $this->per_page);

        return apply_filters( 'uwp_export_users_get_data', $data );
    }

    public function get_export_status() {
        $status = 100;
        return apply_filters( 'uwp_get_export_users_status', $status );
    }

    public function print_rows() {
        $row_data   = '';
        $data       = $this->get_export_data();
        $columns    = $this->get_columns();

        if ( $data ) {
            foreach ( $data as $row ) {
                $i = 1;
                foreach ( $row as $key => $column ) {
                    $row_data .= '"' . addslashes( preg_replace( "/\"/","'", $column ) ) . '"';
                    $row_data .= $i == count( $columns ) ? '' : ',';
                    $i++;
                }
                $row_data .= "\r\n";
            }

            $this->attach_export_data( $row_data );

            return $row_data;
        }

        return false;
    }

    protected function get_export_file() {
        $file = '';

        if ( $this->wp_filesystem->exists( $this->file ) ) {
            $file = $this->wp_filesystem->get_contents( $this->file );
        } else {
            $this->wp_filesystem->put_contents( $this->file, '' );
        }

        return $file;
    }

    protected function attach_export_data( $data = '' ) {
        $filedata   = $this->get_export_file();
        $filedata   .= $data;

        $this->wp_filesystem->put_contents( $this->file, $filedata );

        $rows       = file( $this->file, FILE_SKIP_EMPTY_LINES );
        $columns    = $this->get_columns();
        $columns    = empty( $columns ) ? 0 : 1;

        $this->empty = count( $rows ) == $columns ? true : false;
    }

    public function uwp_get_export_users_status() {
        global $wpdb;
        $data       = $wpdb->get_results("SELECT user_id FROM $this->meta_table_name WHERE 1=1");
        $total      = !empty( $data ) ? count( $data ) : 0;
        $status     = 100;

        if ( $total > 0 ) {
            $status = ( ( $this->per_page * $this->step ) / $total ) * 100;
        }

        if ( $status > 100 ) {
            $status = 100;
        }

        return $status;
    }

    public function uwp_ie_upload_file(){
        $upload_data = array(
            'name'     => $_FILES['import_file']['name'],
            'type'     => $_FILES['import_file']['type'],
            'tmp_name' => $_FILES['import_file']['tmp_name'],
            'error'    => $_FILES['import_file']['error'],
            'size'     => $_FILES['import_file']['size']
        );

        header('Content-Type: text/html; charset=' . get_option('blog_charset'));

        $uploaded_file = wp_handle_upload( $upload_data, array('test_form' => false) );
        if ( isset( $uploaded_file['url'] ) ) {
            $file_loc = $uploaded_file['url'];
            echo $file_loc;
        } else {
            echo 'error';
        }
        exit;
    }

    public function uwp_process_users_import(){

        $response               = array();
        $response['success']    = false;
        $response['msg']        = __( 'Invalid import request found.', 'userswp' );

        if ( empty( $_POST['data'] ) || !current_user_can( 'manage_options' ) ) {
            wp_send_json( $response );
        }

        parse_str( $_POST['data'], $data );

        $this->imp_step   = !empty( $_POST['step'] ) ? absint( $_POST['step'] ) : 1;

        $_REQUEST = (array)$data;
        if ( !( !empty( $_REQUEST['uwp_import_users_nonce'] ) && wp_verify_nonce( $_REQUEST['uwp_import_users_nonce'], 'uwp_import_users_nonce' ) ) ) {
            $response['msg']    = __( 'Security check failed.', 'userswp' );
            wp_send_json( $response );
        }

        $allowed = array('csv');
        $import_file = $data['uwp_import_users_file'];
        $uploads      = wp_upload_dir();
        $csv_file_array = explode( '/', $import_file );
        $csv_filename = end( $csv_file_array );
        $this->path = $uploads['path'].'/'.$csv_filename;

        $ext = pathinfo($csv_filename, PATHINFO_EXTENSION);
        if (!in_array($ext, $allowed)) {
            $response['msg']    = __( 'Invalid file type, please upload .csv file.', 'userswp' );
            wp_send_json( $response );
        }

        $lc_all = setlocale( LC_ALL, 0 );
        setlocale( LC_ALL, 'en_US.UTF-8' );
        if ( ( $handle = fopen( $this->path, "r" ) ) !== false ) {
            while ( ( $data = fgetcsv( $handle, 100000, "," ) ) !== false ) {
                if ( ! empty( $data ) ) {
                    $file[] = $data;
                }
            }
            fclose( $handle );
        }
        setlocale( LC_ALL, $lc_all );

        $this->total_rows = ( ! empty( $file ) && count( $file ) > 1 ) ? count( $file ) - 1 : 0;
        $response['total'] = $this->total_rows;

        $return = $this->process_import_step();
        $done   = $this->get_import_status();

        if ( $return ) {
            $this->imp_step += 1;

            $response['success']    = true;
            $response['msg']        = '';

            if ( $done >= 100 ) {
                $this->imp_step     = 'done';
            }

            $response['data']['step']   = $this->imp_step;
            $response['data']['done']   = $done;
        } else {
            $response['msg']    = __( 'No data found for export.', 'userswp' );
        }

        wp_send_json( $response );

    }

    public function process_import_step() {

        $errors = new WP_Error();
        if(is_null($this->path)){
            $errors->add('no_csv_file', __('No csv file found.','userswp'));
        }

        set_time_limit(0);

        $return = false;$skipped = 0;

        $rows = $this->get_csv_rows($this->imp_step, 1);

        if ( ! empty( $rows ) ) {
            foreach ( $rows as $row ) {
                if( empty($row) ) {
                    $skipped++;
                    continue;
                }

                $username = isset($row['uwp_account_username']) ? $row['uwp_account_username'] : '';
                $email = isset($row['uwp_account_email']) ? $row['uwp_account_email'] : '';
                $display_name = isset($row['uwp_account_display_name']) ? $row['uwp_account_display_name'] : '';
                $password = wp_generate_password();
                $exclude = array('user_id');
                $exclude = apply_filters('uwp_import_exclude_columns', $exclude, $row);

                if((int)$row['user_id'] > 0){
                    $user = get_user_by('ID', $row['user_id']);
                    if(false === $user){
                        $userdata = array(
                            'user_login'  =>  $row['uwp_account_username'],
                            'user_email'  =>  $email,
                            'user_pass'   =>  $password,
                            'display_name'=>  $display_name
                        );
                        $user_id = wp_insert_user( $userdata );
                    } else {
                        if( $user->user_login == $row['uwp_account_username'] ) { //check id passed in csv and existing username are same
                            $user_id = $row['user_id'];
                            if( !empty( $email ) && $email != $user->user_email) {
                                $args = array(
                                    'ID'         => $user_id,
                                    'user_email' => $email,
                                    'display_name' => $display_name
                                );
                                wp_update_user( $args );
                            }
                        } else {
                            $skipped++;
                            continue;
                        }
                    }
                } elseif(isset($row['uwp_account_username']) && username_exists($row['uwp_account_username'])){
                    $user = get_user_by('login', $row['uwp_account_username']);
                    $user_id = $user->ID;
                    $email = $row['uwp_account_email'];
                    if( !empty( $email ) ) {
                        $args = array(
                            'ID'         => $user_id,
                            'user_email' => $email,
                            'display_name' => $display_name
                        );
                        wp_update_user( $args );
                    }
                } elseif(isset($row['uwp_account_email']) && email_exists($row['uwp_account_email'])){
                    $user = get_user_by('email', $row['uwp_account_email']);
                    $user_id = $user->ID;
                } else {
                    $user_id = wp_create_user( $username, $password, $email );
                }

                if( !is_wp_error( $user_id ) ){
                    foreach ($row as $key => $value){
                        if(!in_array($key, $exclude)){
                            $value = maybe_unserialize($value);
                            uwp_update_usermeta($user_id, $key, $value);
                        }
                    }
                } else {
                    $skipped++;
                    continue;
                }
            }
            $return = true;
        }

        if ( $return ) {
            return true;
        } else {
            return $errors;
        }
    }

    public function get_csv_rows( $row = 0, $count = 1 ) {

        $lc_all = setlocale( LC_ALL, 0 ); // Fix issue of fgetcsv ignores special characters when they are at the beginning of line
        setlocale( LC_ALL, 'en_US.UTF-8' );
        $l = $f =0;
        $headers = $file = array();
        $userdata_fields = $this->get_columns();
        if ( ( $handle = fopen( $this->path, "r" ) ) !== false ) {
            while ( ( $line = fgetcsv( $handle, 100000, "," ) ) !== false ) {
                // If the first line is empty, abort
                // If another line is empty, just skip it
                if ( empty( $line ) ) {
                    if ( $l === 0 )
                        break;
                    else
                        continue;
                }

                // If we are on the first line, the columns are the headers
                if ( $l === 0 ) {
                    $headers = $line;
                    $l ++;
                    continue;
                }

                // only get the rows needed
                if ( $row && $count ) {

                    // if we have everything we need then break;
                    if ( $l == $row + $count ) {
                        break;

                        // if its less than the start row then continue;
                    } elseif ( $l && $l < $row ) {
                        $l ++;
                        continue;

                        // if we have the count we need then break;
                    } elseif ( $f > $count ) {
                        break;
                    }
                }

                // Separate user data from meta
                $userdata = $usermeta = array();
                foreach ( $line as $ckey => $column ) {
                    $column_name = $headers[$ckey];
                    $column = trim( $column );
                    if ( in_array( $column_name, $userdata_fields ) ) {
                        $userdata[$column_name] = $column;
                    } else {
                        $usermeta[$column_name] = $column;
                    }
                }

                // A plugin may need to filter the data and meta
                $userdata = apply_filters( 'uwp_import_userdata', $userdata, $usermeta );
                $usermeta = apply_filters( 'uwp_import_usermeta', $usermeta, $userdata );

                if ( ! empty( $userdata ) ) {
                    $file[] = $userdata;
                    $f ++;
                    $l ++;
                }
            }
            fclose( $handle );
        }
        setlocale( LC_ALL, $lc_all );

        return $file;

    }

    public function get_import_status() {
        $status = 100;
        return apply_filters( 'uwp_get_import_users_status', $status );
    }

    public function uwp_get_import_users_status() {

        if ( $this->imp_step >= $this->total_rows ) {
            $status = 100;
        } else {
            $status = ( ( 1 * $this->imp_step ) / $this->total_rows ) * 100;
        }

        return $status;
    }

}
new UsersWP_Import_Export();