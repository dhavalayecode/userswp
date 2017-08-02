<?php
/**
 * Checks whether the current page is of given page type or not.
 *
 * @since   1.0.0
 * @package UsersWP
 * @param   string|bool $type Page type.
 * @return bool
 */
function is_uwp_page($type = false) {
    $page = new Users_WP_Pages();
    return $page->is_page($type);
}

/**
 * Checks whether the current page is register page or not.
 *
 * @since       1.0.0
 * @package     UsersWP
 * @return      bool
 */
function is_uwp_register_page() {
    $page = new Users_WP_Pages();
    return $page->is_register_page();
}

/**
 * Checks whether the current page is login page or not.
 *
 * @since       1.0.0
 * @package     UsersWP
 * @return      bool
 */
function is_uwp_login_page() {
    $page = new Users_WP_Pages();
    return $page->is_login_page();
}

/**
 * Checks whether the current page is forgot password page or not.
 *
 * @since       1.0.0
 * @package     UsersWP
 * @return      bool
 */
function is_uwp_forgot_page() {
    $page = new Users_WP_Pages();
    return $page->is_forgot_page();
}

/**
 * Checks whether the current page is change password page or not.
 *
 * @since       1.0.0
 * @package     UsersWP
 * @return      bool
 */
function is_uwp_change_page() {
    $page = new Users_WP_Pages();
    return $page->is_change_page();
}

/**
 * Checks whether the current page is reset password page or not.
 *
 * @since       1.0.0
 * @package     UsersWP
 * @return      bool
 */
function is_uwp_reset_page() {
    $page = new Users_WP_Pages();
    return $page->is_reset_page();
}

/**
 * Checks whether the current page is account page or not.
 *
 * @since       1.0.0
 * @package     UsersWP
 * @return      bool
 */
function is_uwp_account_page() {
    $page = new Users_WP_Pages();
    return $page->is_account_page();
}

/**
 * Checks whether the current page is profile page or not.
 *
 * @since       1.0.0
 * @package     UsersWP
 * @return      bool
 */
function is_uwp_profile_page() {
    $page = new Users_WP_Pages();
    return $page->is_profile_page();
}

/**
 * Checks whether the current page is users page or not.
 *
 * @since       1.0.0
 * @package     UsersWP
 * @return      bool
 */
function is_uwp_users_page() {
    $page = new Users_WP_Pages();
    return $page->is_users_page();
}

/**
 * Checks whether the current page is multi register page or not.
 *
 * @since       1.0.0
 * @package     UsersWP
 * @return      bool
 */
function is_uwp_multi_register_page() {
    $page = new Users_WP_Pages();
    return $page->is_multi_register_page();
}

/**
 * Checks whether the current page is logged in user profile page or not.
 *
 * @since       1.0.0
 * @package     UsersWP
 * @return      bool
 */
function is_uwp_current_user_profile_page() {
    $page = new Users_WP_Pages();
    return $page->is_current_user_profile_page();
}

/**
 * This method gets fired during plugin activation.
 *
 * @since       1.0.0
 * @package     UsersWP
 * @return      void
 */
function uwp_get_pages() {
    $page = new Users_WP_Pages();
    return $page->get_pages();
}

/**
 * This method gets fired during plugin activation.
 *
 * @since       1.0.0
 * @package     UsersWP
 * @return      void
 */
function uwp_get_page_slug($page_type = 'register_page') {
    $page = new Users_WP_Pages();
    return $page->get_page_slug($page_type);
}

/**
 * This method gets fired during plugin activation.
 *
 * @since       1.0.0
 * @package     UsersWP
 * @return      void
 */
function uwp_create_page($slug, $option, $page_title = '', $page_content = '', $post_parent = 0, $status = 'publish') {
    $page = new Users_WP_Pages();
    $page->create_page($slug, $option, $page_title, $page_content, $post_parent, $status);
}

/**
 * This method gets fired during plugin activation.
 *
 * @since       1.0.0
 * @package     UsersWP
 * @return      void
 */
function uwp_generate_default_pages() {
    $page = new Users_WP_Pages();
    $page->generate_default_pages();
}