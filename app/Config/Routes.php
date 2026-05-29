<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// --- Auth + User management ---
$routes->get('/',                       'user::login');
$routes->get('login',                   'user::login');
$routes->post('login',                  'user::do_login');
$routes->get('logout',                  'user::logout');

// Forced password rotation (and voluntary password change)
$routes->get('password/change',         'user::password_change');
$routes->post('password/change',        'user::password_change_save');

$routes->get('users',                     'user::index');
$routes->get('users/data_table',          'user::data_table');
$routes->get('users/add',                 'user::add');
$routes->post('users/save',             'user::save');
$routes->get('users/edit/(:num)',       'user::edit/$1');
$routes->post('users/update/(:num)',    'user::update/$1');
// Destructive endpoints intentionally require POST so they can't be
// triggered by `<img src=...>` or browser prefetch of an attacker-crafted
// link. The UI submits them via JS-built hidden forms (see initConfirmLinks).
$routes->post('users/delete/(:num)',    'user::delete/$1');
$routes->get('users/check_user_id',     'user::check_user_id');
$routes->get('users/active_json',       'user::active_json');
$routes->post('users/update_theme',     'user::update_theme');

// Per-user dashboard preferences (KPI visibility, default project, default trend range).
$routes->get('me/dashboard',            'user::me_dashboard');
$routes->post('me/dashboard',           'user::me_dashboard_save');

// Per-user notification preferences (project × severity opt-out matrix).
$routes->get('me/notifications',        'user::me_notifications');
$routes->post('me/notifications',       'user::me_notifications_save');

// Roles — define / rename / delete operator and administrator roles.
// Permission-gated via module_permissions ('roles' module); built-ins
// (super_admin and any rows with is_builtin=1) are protected from
// deletion. module_permissions iterates the live roles table.
$routes->get('roles',                   'user::roles');
$routes->get('roles/add',               'user::role_add');
$routes->post('roles/save',             'user::role_save');
$routes->get('roles/edit/(:any)',       'user::role_edit/$1');
$routes->post('roles/update/(:any)',    'user::role_update/$1');
$routes->post('roles/delete/(:any)',    'user::role_delete/$1');

// --- Everything else ---

// ===== Dashboard =====
$routes->get('dashboard',               'app::dashboard');
$routes->get('dashboard/trend',         'app::dashboard_trend');

// ===== Projects =====
$routes->get('projects',                  'app::projects');
$routes->get('projects/data_table',       'app::projects_data_table');
$routes->get('projects/add',              'app::project_add');
$routes->post('projects/save',          'app::project_save');
$routes->get('projects/edit/(:num)',    'app::project_edit/$1');
$routes->post('projects/update/(:num)', 'app::project_update/$1');
$routes->post('projects/delete/(:num)', 'app::project_delete/$1');

// ===== Flows + States =====
$routes->get('flows',                         'app::flows');
$routes->get('flows/data_table',              'app::flows_data_table');
$routes->get('flows/add',                     'app::flow_add');
$routes->post('flows/save',                 'app::flow_save');
$routes->get('flows/edit/(:num)',           'app::flow_edit/$1');
$routes->post('flows/update/(:num)',        'app::flow_update/$1');
$routes->post('flows/delete/(:num)',        'app::flow_delete/$1');
$routes->get('flows/states/(:num)',         'app::flow_states/$1');
$routes->post('flows/save_state',           'app::state_save');
$routes->post('flows/delete_state/(:num)',  'app::state_delete/$1');
$routes->post('flows/reorder_states',       'app::state_reorder');

// ===== Alert Definitions =====
$routes->get('alerts',                    'app::alerts');
$routes->get('alerts/data_table',         'app::alerts_data_table');
$routes->get('alerts/add',                'app::alert_add');
$routes->post('alerts/save',            'app::alert_save');
$routes->get('alerts/edit/(:num)',      'app::alert_edit/$1');
$routes->post('alerts/update/(:num)',   'app::alert_update/$1');
$routes->post('alerts/delete/(:num)',   'app::alert_delete/$1');

// ===== Escalation =====
$routes->get('escalation',                            'app::escalation');
$routes->post('escalation/save',                      'app::escalation_save');
$routes->post('escalation/delete/(:num)',             'app::escalation_delete/$1');
$routes->get('escalation/states_by_flow/(:num)',      'app::escalation_states_by_flow/$1');

// ===== API Keys =====
$routes->get('api_keys',                  'app::api_keys');
$routes->post('api_keys/generate',        'app::api_key_generate');
$routes->post('api_keys/toggle/(:num)',   'app::api_key_toggle/$1');

// ===== Header bell badge =====
$routes->get('notifications/actionable_count', 'app::actionable_count');
$routes->get('notifications/recent',           'app::notifications_recent');

// ===== Settings (admin) =====
$routes->get('settings',                  'app::settings');
$routes->post('settings/save',            'app::settings_save');
$routes->post('settings/send_test_email', 'app::settings_send_test_email');
$routes->post('settings/bump_asset_version', 'app::settings_bump_asset_version');

// ===== Module Control Panel =====
$routes->get('module_control_panel',                  'app::module_control_panel');
$routes->post('module_control_panel/save',            'app::module_control_panel_save');

// ===== Tickets (UI) =====
$routes->get('tickets',                                    'app::tickets_my');
$routes->get('tickets/all',                                'app::tickets_all');
$routes->get('tickets/data_table',                         'app::ticket_data_table');
$routes->get('tickets/create',                             'app::ticket_create');
$routes->post('tickets/save',                              'app::ticket_save');
$routes->get('tickets/detail/(:any)',                      'app::ticket_detail/$1');
$routes->post('tickets/action/(:any)',                     'app::ticket_action/$1');
$routes->post('tickets/move_state/(:any)',                 'app::ticket_move_state/$1');
$routes->post('tickets/attach/(:any)',                     'app::ticket_attach/$1');
$routes->get('tickets/download/(:segment)/(:num)',         'app::ticket_download/$1/$2');
$routes->post('tickets/assign/(:any)',                     'app::ticket_assign/$1');
$routes->post('tickets/resolve/(:any)',                    'app::ticket_resolve/$1');
$routes->post('tickets/close/(:any)',                      'app::ticket_close/$1');
$routes->get('tickets/flows_by_project/(:num)',            'app::ticket_flows_by_project/$1');
$routes->get('tickets/export',                             'app::tickets_export');
$routes->post('tickets/bulk',                              'app::tickets_bulk');
$routes->post('tickets/saved/save',                        'app::tickets_saved_save');
$routes->post('tickets/saved/delete/(:num)',               'app::tickets_saved_delete/$1');

// ===== Activity Log (super_admin audit feed) =====
$routes->get('activity_logs',             'app::activity_logs');
$routes->get('activity_logs/data_table',  'app::activity_logs_data_table');
$routes->get('activity_logs/export',      'app::activity_logs_export');

// ===== REST API (external systems, X-API-KEY auth) =====
$routes->post('api/raise',               'app::api_raise');
$routes->get('api/alert/(:any)',         'app::api_show/$1');
$routes->post('api/alert/(:any)/update', 'app::api_update/$1');
$routes->get('api/alerts',               'app::api_index');
$routes->get('api/flows',                'app::api_flows');
