<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/userguide3/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'Auth';
$route['auth']               = 'Auth/index';
$route['auth/login']         = 'Auth/login';
$route['auth/logout']        = 'Auth/logout';
$route['dashboard']          = 'Dashboard/index';
$route['users']              = 'Users/index';
$route['users/datatable']    = 'Users/get_datatable';
$route['users/create']       = 'Users/create';
$route['users/get/(:num)']   = 'Users/get_user/$1';
$route['users/update/(:num)']= 'Users/update/$1';
$route['users/delete/(:num)']= 'Users/delete/$1';
$route['users/toggle/(:num)']= 'Users/toggle_status/$1';
$route['setup']              = 'Setup/index';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'Auth';
$route['auth']               = 'Auth/index';
$route['auth/login']         = 'Auth/login';
$route['auth/logout']        = 'Auth/logout';
$route['dashboard']          = 'Dashboard/index';
$route['users']              = 'Users/index';
$route['users/datatable']    = 'Users/get_datatable';
$route['users/create']       = 'Users/create';
$route['users/get/(:num)']   = 'Users/get_user/$1';
$route['users/update/(:num)']= 'Users/update/$1';
$route['users/delete/(:num)']= 'Users/delete/$1';
$route['users/toggle/(:num)']= 'Users/toggle_status/$1';
$route['setup']                        = 'Setup/index';
$route['profile/update']               = 'Profile/update';
$route['payroll']                      = 'Payroll/index';
$route['payroll/save_settings']        = 'Payroll/save_settings';
$route['payroll/set_borrow_deduction'] = 'Payroll/set_borrow_deduction';
$route['payroll/export/(:any)/(:any)']      = 'Payroll/export/$1/$2';
$route['payroll/export/(:any)']             = 'Payroll/export/$1';
$route['payroll/print_view/(:any)/(:any)']  = 'Payroll/print_view/$1/$2';
$route['payroll/print_view/(:any)']         = 'Payroll/print_view/$1';
$route['payroll/sign_sheet/(:any)/(:any)']  = 'Payroll/sign_sheet/$1/$2';
$route['payroll/sign_sheet/(:any)']         = 'Payroll/sign_sheet/$1';
$route['payroll/line_items']                = 'Payroll/get_line_items';
$route['payroll/add_line_item']             = 'Payroll/add_line_item';
$route['payroll/delete_line_item/(:num)']   = 'Payroll/delete_line_item/$1';
// ── Employee Routes ──────────────────────────────────────────────
$route['employees']                              = 'Employees/index';
$route['employees/datatable']                    = 'Employees/get_datatable';
$route['employees/view/(:num)']                  = 'Employees/view/$1';
$route['employees/save_payroll_info/(:num)']     = 'Employees/save_payroll_info/$1';
$route['employees/attendance/(:num)']            = 'Employees/get_attendance/$1';
$route['employees/salary_requests/(:num)']       = 'Employees/get_salary_requests/$1';
$route['employees/update_info/(:num)']              = 'Employees/update_info/$1';
$route['employees/save_emergency_contact/(:num)']   = 'Employees/save_emergency_contact/$1';
$route['employees/set_project/(:num)']              = 'Employees/set_project/$1';
$route['employees/project_history/(:num)']          = 'Employees/get_project_history/$1';
$route['employees/files/(:num)']                     = 'Employees/get_files/$1';
$route['employees/upload_file/(:num)']               = 'Employees/upload_file/$1';
$route['employees/archive_file/(:num)']              = 'Employees/archive_file/$1';
$route['employees/restore_file/(:num)']              = 'Employees/restore_file/$1';

// ── Entrance Scan Station (fingerprint kiosk) ────────────────────
$route['scanner']                            = 'Scanner/index';
$route['scanner/unlock']                     = 'Scanner/unlock';
$route['scanner/lock']                       = 'Scanner/lock';
$route['scanner/verify_password']            = 'Scanner/verify_password';
$route['scanner/challenge']                  = 'Scanner/challenge';
$route['scanner/scan_verify']                = 'Scanner/scan_verify';
$route['scanner/scan_code']                  = 'Scanner/scan_code';
$route['scanner/today_logs']                 = 'Scanner/today_logs';
$route['scanner/employees']                  = 'Scanner/employees';
$route['scanner/register_options']           = 'Scanner/register_options';
$route['scanner/register_verify']            = 'Scanner/register_verify';
$route['scanner/fingerprints']               = 'Scanner/fingerprints';
$route['scanner/delete_fingerprint/(:num)']  = 'Scanner/delete_fingerprint/$1';
$route['scanner/get_settings']               = 'Scanner/get_settings';
$route['scanner/save_settings']              = 'Scanner/save_settings';

// ── Biometric Devices (ZKTeco) ───────────────────────────────────
// ADMS push protocol — called by the terminal itself, no auth/session
$route['iclock/cdata']      = 'Iclock/cdata';
$route['iclock/getrequest'] = 'Iclock/getrequest';
$route['iclock/devicecmd']  = 'Iclock/devicecmd';
$route['iclock/fdata']      = 'Iclock/fdata';
// Admin page: terminal status, offline file import, punch log
$route['devices']           = 'Devices/index';
$route['devices/punches']   = 'Devices/punches';
$route['devices/upload']    = 'Devices/upload';
$route['devices/reprocess'] = 'Devices/reprocess';

// ── System Config Routes ─────────────────────────────────────────
$route['system_config']                              = 'SystemConfig/index';
$route['system_config/system_settings']              = 'SystemConfig/system_settings';
$route['system_config/save_system_settings']         = 'SystemConfig/save_system_settings';
$route['system_config/attendance_settings']          = 'SystemConfig/attendance_settings';
$route['system_config/save_attendance_settings']     = 'SystemConfig/save_attendance_settings';
$route['system_config/job_roles_datatable']          = 'SystemConfig/job_roles_datatable';
$route['system_config/create_job_role']              = 'SystemConfig/create_job_role';
$route['system_config/get_job_role/(:num)']          = 'SystemConfig/get_job_role/$1';
$route['system_config/update_job_role/(:num)']       = 'SystemConfig/update_job_role/$1';
$route['system_config/delete_job_role/(:num)']       = 'SystemConfig/delete_job_role/$1';
$route['system_config/projects_datatable']            = 'SystemConfig/projects_datatable';
$route['system_config/create_project']                = 'SystemConfig/create_project';
$route['system_config/get_project/(:num)']            = 'SystemConfig/get_project/$1';
$route['system_config/update_project/(:num)']         = 'SystemConfig/update_project/$1';
$route['system_config/delete_project/(:num)']         = 'SystemConfig/delete_project/$1';
$route['system_config/project_heads/(:num)']           = 'SystemConfig/project_heads/$1';
$route['system_config/add_project_head/(:num)']        = 'SystemConfig/add_project_head/$1';
$route['system_config/remove_project_head/(:num)']     = 'SystemConfig/remove_project_head/$1';

$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;
