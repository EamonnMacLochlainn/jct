
--
-- Table structure for table app_screen_user
--

CREATE TABLE app_screen_user (
  tbl_id int(10) UNSIGNED NOT NULL,
  id int(10) NOT NULL,
  role_id tinyint(2) UNSIGNED NOT NULL,
  app_slug tinytext COLLATE latin1_bin NOT NULL,
  module tinytext COLLATE latin1_bin,
  model tinytext COLLATE latin1_bin,
  method text COLLATE latin1_bin,
  updated datetime NOT NULL,
  updated_by int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_bin;

-- --------------------------------------------------------

--
-- Table structure for table calendar_open_date
--

CREATE TABLE calendar_open_date (
  tbl_id int(11) NOT NULL,
  year_starting year(4) NOT NULL,
  open_date date NOT NULL,
  day_name text CHARACTER SET latin1 NOT NULL,
  is_first_in_week tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  is_last_in_week tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  updated datetime NOT NULL,
  updated_by int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- --------------------------------------------------------

--
-- Table structure for table calendar_term
--

CREATE TABLE calendar_term (
  id int(10) UNSIGNED NOT NULL,
  year_starting year(4) NOT NULL,
  title varchar(35) COLLATE utf8_swedish_ci NOT NULL,
  start_date date NOT NULL,
  end_date date NOT NULL,
  updated datetime NOT NULL,
  updated_by int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table dashboard_notification
--

CREATE TABLE dashboard_notification (
  id int(10) UNSIGNED NOT NULL,
  app_slug tinytext CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  app_specific_id int(10) UNSIGNED DEFAULT NULL,
  title varchar(100) NOT NULL,
  content varchar(255) DEFAULT NULL,
  show_as_dialog tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  redirect tinyint(1) UNSIGNED NOT NULL DEFAULT '1',
  redirect_url tinytext CHARACTER SET latin1 COLLATE latin1_bin,
  published datetime NOT NULL,
  status tinytext CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  created datetime NOT NULL,
  updated datetime NOT NULL,
  updated_by int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table dashboard_notification_user
--

CREATE TABLE dashboard_notification_user (
  tbl_id int(10) UNSIGNED NOT NULL,
  id int(10) UNSIGNED NOT NULL,
  role_id tinyint(2) UNSIGNED NOT NULL,
  notification_id int(10) UNSIGNED NOT NULL,
  is_read tinyint(1) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table email_account
--

CREATE TABLE email_account (
  id int(10) UNSIGNED NOT NULL,
  mail_server tinytext CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  username tinytext CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  mail_password varchar(255) COLLATE utf8_bin NOT NULL,
  mail_port smallint(3) NOT NULL,
  type tinytext CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  smtp_auth tinyint(1) NOT NULL DEFAULT '1',
  smtp_encryption tinytext CHARACTER SET latin1 COLLATE latin1_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- --------------------------------------------------------

--
-- Table structure for table email_template
--

CREATE TABLE email_template (
  id int(10) UNSIGNED NOT NULL,
  app_slug tinytext CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  title varchar(50) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  content varchar(5000) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf32 COLLATE=utf32_hungarian_ci;

--
-- Dumping data for table email_template
--

INSERT INTO email_template (id, app_slug, title, content) VALUES
(1, 'pm_scheduler', 'Reservation Notification', '<h2>[event_title]</h2>\n<p>Dear Parent/Guardian,</p>\n<p>We will be holding our Parent-Teacher meetings on [event_dates_en].</p>\n<p>The following reservation(s) have been made for your enroled children:</p>\n<p>[family_meetings_schedule_en]</p>\n<p>If you, the parent/guardian, is unable to attend the above at the arranged time(s), or wish for someone else to represent you, we ask you let the office or M&uacute;inteoir know as soon as possible.</p>\n<p>Go raibh m&iacute;le maith agat.</p>'),
(2, 'pm_scheduler', 'Guardian Invitiation', '<p>Dear Parent / Guardian,</p>\n<p>We will be holding [event_title] meetings on the [event_dates_en].</p>\n<p>We will be using our in-school database provider (DataBiz Solutions) to facilitate the online booking of dates and times for these meetings. We ask that you make use of this facility (instructions below) at the earliest opportunity in order to reserve a convenient time-slot for your own meeting(s). The website address is:</p>\n<p><a href=\"https://databizsolutions.ie\">https://databizsolutions.ie</a></p>\n<p>The following meetings are planned:</p>\n<p>[family_meetings_planned_en]</p>\n<p>&nbsp;</p>\n<p>&nbsp;</p>\n<p>There are two steps to follow:</p>\n<ul>\n<li>Register with DataBiz Solutions to receive a password, and</li>\n<li>Log in and reserve your preferred time-slot(s) for your meeting(s)</li>\n</ul>\n<p><strong>To Register with the DataBiz Solutions platform</strong>:</p>\n<p>If you have not already registered...</p>\n<ul>\n<li>Follow the link (<a href=\"https://databizsolutions.ie\">https://databizsolutions.ie</a>) to visit the DataBiz Solutions website.</li>\n<li>Click on the \'DataBiz Apps\' link at the top of your screen.</li>\n<li>When presented with the login form, click the \'Sign Up\' option.</li>\n<li>Enter your email address (i.e. the email address that recieved this message) and mobile number in the pop-up screen and click \'Send Request\'.</li>\n<li>Open your email inbox and, after a few moments, you should receive an email with your new password (If the email is not in your inbox please check your Junk/Spam folder).</li>\n</ul>\n<p><strong>To log in and reserve your time-slot</strong>:</p>\n<ul>\n<li>Return to the login form and enter your email address and the new password that you have just received in your email and click \'Log In\'.</li>\n<li>Click on the Parent/Teacher meeting link provided in the \'Notifications\' panel.</li>\n<li>Follow the instructions on-screen to reserve your preferred meeting time.</li>\n</ul>\n<p>Note that you can change your provided password to something more memorable via the \'User\' tab at the top of your screen, once you have successfully logged in.</p>\n<p>&nbsp;</p>'),
(3, 'payments', 'New Fee Notification', '<p>Dear Parent / Guardian,</p>\n<p>Please note that the \'[charge_title]\' Charge is now payable via the DataBiz Solutions payment portal. The Fee(s) applicable for this Charge are:</p>\n<p>[family_fees_outstanding_en]</p>\n<p>&nbsp;</p>\n<p>If you have not previously made an online payment via that system, there are two steps to follow:</p>\n<ul>\n<li>Register with DataBiz Solutions to receive a password, and</li>\n<li>Log in and make your payment(s) using a credit or debit card.</li>\n</ul>\n<p>&nbsp;</p>\n<p><strong>To Register with the DataBiz Solutions platform</strong>:</p>\n<p>If you have not already registered...</p>\n<ul>\n<li>Follow the link (<a href=\"https://databizsolutions.ie\">https://databizsolutions.ie</a>) to visit the DataBiz Solutions website.</li>\n<li>Click on the \'DataBiz Apps\' link at the top of your screen.</li>\n<li>When presented with the login form, click the \'Sign Up\' option.</li>\n<li>Enter your email address (i.e. the email address that recieved this message) and mobile number in the pop-up screen and click \'Send Request\'.</li>\n<li>Open your email inbox and, after a few moments, you should receive an email with your new password (If the email is not in your inbox please check your Junk/Spam folder).</li>\n</ul>\n<p><strong>To log in and make a Payment</strong>:</p>\n<ul>\n<li>Return to the login form and enter your email address and the new password that you have just received in your email and click \'Log In\'*</li>\n<li>Click on the Payments Fee link provided in the \'Notifications\' panel, or click on the \'Payments\' icon.</li>\n<li>Select the Fee you wish to pay (you can change the amount payable, should you wish to pay in installments).</li>\n<li>Click on the \'Pay Now\' button at the bottom of the screen, and submit your credit card details via the popup that appears.</li>\n</ul>\n<p>&nbsp;</p>\n<p>*<em>Note that you can change your provided password to something more memorable via the \'User\' tab at the top of your screen, once you have successfully logged in.</em></p>\n<p>&nbsp;</p>'),
(4, 'payments', 'Fee Reminder', '<p>Dear Parent / Guardian,</p>\n<p>Please note that Fee(s) for the \'[charge_title]\' Charge remain outstanding. These Fee(s) are payable via the DataBiz Solutions payment portal.</p>\n<p>[family_fees_outstanding_en]</p>\n<p>&nbsp;</p>\n<p>If you have not previously made an online payment via that system, there are two steps to follow:</p>\n<ul>\n<li>Register with DataBiz Solutions to receive a password, and</li>\n<li>Log in and make your payment(s) using a credit or debit card.</li>\n</ul>\n<p>&nbsp;</p>\n<p><strong>To Register with the DataBiz Solutions platform</strong>:</p>\n<p>If you have not already registered...</p>\n<ul>\n<li>Follow the link (<a href=\"https://databizsolutions.ie\">https://databizsolutions.ie</a>) to visit the DataBiz Solutions website.</li>\n<li>Click on the \'DataBiz Apps\' link at the top of your screen.</li>\n<li>When presented with the login form, click the \'Sign Up\' option.</li>\n<li>Enter your email address (i.e. the email address that recieved this message) and mobile number in the pop-up screen and click \'Send Request\'.</li>\n<li>Open your email inbox and, after a few moments, you should receive an email with your new password (If the email is not in your inbox please check your Junk/Spam folder).</li>\n</ul>\n<p><strong>To log in and make a Payment</strong>:</p>\n<ul>\n<li>Return to the login form and enter your email address and the new password that you have just received in your email and click \'Log In\'*</li>\n<li>Click on the Payments Fee link provided in the \'Notifications\' panel, or click on the \'Payments\' icon.</li>\n<li>Select the Fee you wish to pay (you can change the amount payable, should you wish to pay in installments).</li>\n<li>Click on the \'Pay Now\' button at the bottom of the screen, and submit your credit card details via the popup that appears.</li>\n</ul>\n<p>&nbsp;</p>\n<p>*<em>Note that you can change your provided password to something more memorable via the \'User\' tab at the top of your screen, once you have successfully logged in.</em></p>\n<p>&nbsp;</p>');

-- --------------------------------------------------------

--
-- Table structure for table group_class
--

CREATE TABLE group_class (
  id int(10) UNSIGNED NOT NULL,
  ext_id smallint(3) DEFAULT NULL,
  title varchar(35) COLLATE utf8_bin NOT NULL,
  title_eng_gae_variant varchar(35) COLLATE utf8_bin DEFAULT NULL,
  abbr varchar(7) COLLATE utf8_bin NOT NULL,
  group_super_id int(10) UNSIGNED NOT NULL DEFAULT '0',
  gender enum('A','M','F') CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT 'A',
  type_id tinyint(2) UNSIGNED NOT NULL DEFAULT '0',
  active tinyint(1) NOT NULL DEFAULT '0',
  active_begin date NOT NULL,
  active_end date DEFAULT NULL,
  weight tinyint(2) UNSIGNED NOT NULL DEFAULT '0',
  updated datetime NOT NULL,
  updated_by int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- --------------------------------------------------------

--
-- Table structure for table group_class_leader
--

CREATE TABLE group_class_leader (
  tbl_id int(6) UNSIGNED NOT NULL,
  id int(10) UNSIGNED NOT NULL,
  group_class_id smallint(5) UNSIGNED NOT NULL,
  group_super_id smallint(5) UNSIGNED NOT NULL,
  leader_begin date NOT NULL,
  leader_end date DEFAULT NULL,
  updated datetime NOT NULL,
  updated_by int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table group_custom
--

CREATE TABLE group_custom (
  id tinyint(2) UNSIGNED NOT NULL,
  ext_id smallint(3) DEFAULT NULL,
  title varchar(50) COLLATE utf8_bin NOT NULL,
  title_eng_gae_variant varchar(35) COLLATE utf8_bin DEFAULT NULL,
  abbr varchar(7) COLLATE utf8_bin NOT NULL,
  active tinyint(1) NOT NULL DEFAULT '0',
  weight tinyint(2) UNSIGNED NOT NULL DEFAULT '0',
  updated datetime NOT NULL,
  updated_by int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- --------------------------------------------------------

--
-- Table structure for table group_custom_leader
--

CREATE TABLE group_custom_leader (
  tbl_id int(6) UNSIGNED NOT NULL,
  id int(10) UNSIGNED NOT NULL,
  group_custom_id tinyint(2) UNSIGNED NOT NULL,
  leader_begin date NOT NULL,
  leader_end date DEFAULT NULL,
  updated datetime NOT NULL,
  updated_by int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table group_staff
--

CREATE TABLE group_staff (
  id tinyint(2) NOT NULL,
  ext_id smallint(3) DEFAULT NULL,
  title varchar(35) COLLATE utf8_bin NOT NULL,
  title_eng_gae_variant varchar(35) COLLATE utf8_bin DEFAULT NULL,
  abbr varchar(7) COLLATE utf8_bin NOT NULL,
  active tinyint(1) NOT NULL DEFAULT '0',
  weight tinyint(2) UNSIGNED NOT NULL DEFAULT '0',
  updated datetime NOT NULL,
  updated_by int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- --------------------------------------------------------

--
-- Table structure for table group_staff_staff
--

CREATE TABLE group_staff_staff (
  tbl_id int(6) UNSIGNED NOT NULL,
  id int(10) UNSIGNED NOT NULL,
  group_staff_id tinyint(2) UNSIGNED NOT NULL,
  in_group_begin date NOT NULL,
  in_group_end date DEFAULT NULL,
  updated datetime NOT NULL,
  updated_by int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table group_super
--

CREATE TABLE group_super (
  id int(10) UNSIGNED NOT NULL,
  ext_id smallint(6) DEFAULT NULL,
  title varchar(35) COLLATE utf8_bin NOT NULL,
  title_eng_gae_variant varchar(35) COLLATE utf8_bin DEFAULT NULL,
  abbr varchar(7) COLLATE utf8_bin NOT NULL,
  active tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  active_begin date NOT NULL,
  active_end date DEFAULT NULL,
  current tinyint(1) UNSIGNED NOT NULL DEFAULT '1',
  academic_band tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '1 - 4 for academic classes',
  academic_order tinyint(2) UNSIGNED NOT NULL DEFAULT '0' COMMENT '1 - 8 for academic classes',
  weight tinyint(2) UNSIGNED NOT NULL DEFAULT '0',
  updated datetime NOT NULL,
  updated_by int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- --------------------------------------------------------

--
-- Table structure for table manager_setting
--

CREATE TABLE manager_setting (
  tbl_id int(10) UNSIGNED NOT NULL,
  setting_key tinytext NOT NULL,
  setting_value varchar(50) DEFAULT NULL,
  setting_year_starting year(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table member_group_assistant
--

CREATE TABLE member_group_assistant (
  tbl_id int(6) UNSIGNED NOT NULL,
  id int(10) UNSIGNED NOT NULL,
  group_assistant_id int(10) UNSIGNED NOT NULL,
  assistant_begin date NOT NULL,
  assistant_end date DEFAULT NULL,
  updated datetime NOT NULL,
  updated_by int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table member_group_class
--

CREATE TABLE member_group_class (
  tbl_id int(6) UNSIGNED NOT NULL,
  id int(10) UNSIGNED NOT NULL,
  group_class_id int(10) UNSIGNED NOT NULL,
  group_super_id int(10) UNSIGNED NOT NULL,
  in_group_begin date NOT NULL,
  in_group_end date DEFAULT NULL,
  updated datetime NOT NULL,
  updated_by int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table member_group_custom
--

CREATE TABLE member_group_custom (
  tbl_id int(6) UNSIGNED NOT NULL,
  id int(10) UNSIGNED NOT NULL,
  group_custom_id tinyint(2) UNSIGNED NOT NULL,
  in_group_begin date NOT NULL,
  in_group_end date DEFAULT NULL,
  updated datetime NOT NULL,
  updated_by int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table member_guardian
--

CREATE TABLE member_guardian (
  tbl_id int(6) UNSIGNED NOT NULL,
  id int(10) UNSIGNED NOT NULL,
  guardian_id int(10) UNSIGNED NOT NULL,
  guardian_begin date NOT NULL,
  guardian_end date DEFAULT NULL,
  is_default tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  include_in_email tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  include_in_text tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  include_in_letter tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  updated datetime NOT NULL,
  updated_by int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table member_member_assistant
--

CREATE TABLE member_member_assistant (
  tbl_id int(6) UNSIGNED NOT NULL,
  id int(10) UNSIGNED NOT NULL,
  member_assistant_id int(10) UNSIGNED NOT NULL,
  assistant_begin date NOT NULL,
  assistant_end date DEFAULT NULL,
  updated datetime NOT NULL,
  updated_by int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table member_sibling
--

CREATE TABLE member_sibling (
  tbl_id int(6) UNSIGNED NOT NULL,
  id int(10) UNSIGNED NOT NULL,
  sibling_id int(10) UNSIGNED NOT NULL,
  sibling_begin date NOT NULL,
  sibling_end date DEFAULT NULL,
  updated datetime NOT NULL,
  updated_by int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table person
--

CREATE TABLE person (
  id int(10) UNSIGNED NOT NULL,
  ext_id tinytext CHARACTER SET latin1 COLLATE latin1_bin,
  fname varchar(35) DEFAULT NULL,
  lname varchar(35) DEFAULT NULL,
  indexed_lname tinytext CHARACTER SET latin1 COLLATE latin1_bin,
  salute_name varchar(50) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  salt_id tinyint(2) UNSIGNED NOT NULL DEFAULT '0',
  landline tinytext CHARACTER SET latin1 COLLATE latin1_bin,
  mobile tinytext CHARACTER SET latin1 COLLATE latin1_bin,
  email tinytext CHARACTER SET latin1 COLLATE latin1_bin,
  landline_alt tinytext CHARACTER SET latin1 COLLATE latin1_bin,
  mobile_alt tinytext CHARACTER SET latin1 COLLATE latin1_bin,
  email_alt tinytext CHARACTER SET latin1 COLLATE latin1_bin,
  add1 varchar(35) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  add2 varchar(35) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  add3 varchar(35) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  add4 varchar(35) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  city_town varchar(35) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  postcode tinyint(2) UNSIGNED NOT NULL DEFAULT '0',
  eircode tinytext CHARACTER SET latin1 COLLATE latin1_bin,
  county_id int(10) UNSIGNED DEFAULT NULL,
  country_id int(10) UNSIGNED DEFAULT NULL,
  show_county tinyint(1) UNSIGNED NOT NULL DEFAULT '1',
  is_staff tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  is_guardian tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  is_member tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  active tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  created datetime NOT NULL,
  deactivated date DEFAULT NULL,
  updated datetime NOT NULL,
  updated_by int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table prm_country
--

CREATE TABLE prm_country (
  id int(10) UNSIGNED NOT NULL,
  title varchar(100) COLLATE utf8_bin NOT NULL,
  pod_restrained tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  parent_id int(10) UNSIGNED DEFAULT NULL,
  attribute tinytext CHARACTER SET latin1 COLLATE latin1_bin,
  weight tinyint(4) UNSIGNED NOT NULL DEFAULT '0',
  active tinyint(1) UNSIGNED NOT NULL DEFAULT '1',
  updated datetime NOT NULL,
  updated_by int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Dumping data for table prm_country
--

INSERT INTO prm_country (id, title, pod_restrained, parent_id, attribute, weight, active, updated, updated_by) VALUES
(4, 'Afghanistan', 1, NULL, 'AF', 0, 0, '2017-07-10 16:17:17', 0),
(8, 'Albania', 1, NULL, 'AL', 0, 0, '2017-07-10 16:17:17', 0),
(12, 'Algeria', 1, NULL, 'DZ', 0, 0, '2017-07-10 16:17:17', 0),
(20, 'Andorra', 1, NULL, 'AD', 0, 0, '2017-07-10 16:17:17', 0),
(24, 'Angola', 1, NULL, 'AO', 0, 0, '2017-07-10 16:17:17', 0),
(28, 'Antigua And Barbuda', 1, NULL, 'AG', 0, 0, '2017-07-10 16:17:17', 0),
(32, 'Argentina', 1, NULL, 'AR', 0, 0, '2017-07-10 16:17:17', 0),
(34, 'Armenia', 1, NULL, 'AM', 0, 0, '2017-07-10 16:17:17', 0),
(36, 'Australia', 1, NULL, 'AU', 0, 0, '2017-07-10 16:17:17', 0),
(38, 'Azerbaijan', 1, NULL, 'AZ', 0, 0, '2017-07-10 16:17:17', 0),
(40, 'Austria', 1, NULL, 'AT', 0, 0, '2017-07-10 16:17:17', 0),
(44, 'Bahamas, The', 1, NULL, 'BS', 0, 0, '2017-07-10 16:17:17', 0),
(48, 'Bahrain', 1, NULL, 'BH', 0, 0, '2017-07-10 16:17:17', 0),
(50, 'Bangladesh', 1, NULL, 'BD', 0, 0, '2017-07-10 16:17:17', 0),
(52, 'Barbados', 1, NULL, 'BB', 0, 0, '2017-07-10 16:17:17', 0),
(56, 'Belgium', 1, NULL, 'BE', 6, 1, '2018-12-20 09:39:15', 3303),
(57, 'Bosnia And Herzegovina', 1, NULL, 'BA', 0, 0, '2017-07-10 16:17:17', 0),
(58, 'Belize', 1, NULL, 'BZ', 0, 0, '2017-07-10 16:17:17', 0),
(64, 'Bhutan', 1, NULL, 'BT', 0, 0, '2017-07-10 16:17:17', 0),
(68, 'Bolivia', 1, NULL, 'BO', 0, 0, '2017-07-10 16:17:17', 0),
(72, 'Botswana', 1, NULL, 'BW', 0, 0, '2017-07-10 16:17:17', 0),
(76, 'Brazil', 1, NULL, 'BR', 12, 1, '2018-12-20 09:39:15', 3303),
(96, 'Brunei Darussalam', 1, NULL, 'BN', 0, 0, '2017-07-10 16:17:17', 0),
(100, 'Bulgaria', 1, NULL, 'BG', 0, 0, '2017-07-10 16:17:17', 0),
(102, 'Croatia', 1, NULL, 'HR', 0, 0, '2017-07-10 16:17:17', 0),
(104, 'Myanmar (Burma)', 1, NULL, 'MM', 0, 0, '2017-07-10 16:17:17', 0),
(108, 'Burundi', 1, NULL, 'BI', 0, 0, '2017-07-10 16:17:17', 0),
(120, 'Cameroon', 1, NULL, 'CM', 0, 0, '2017-07-10 16:17:17', 0),
(124, 'Canada', 1, NULL, 'CA', 0, 0, '2017-07-10 16:17:17', 0),
(132, 'Cape Verde', 1, NULL, 'CV', 0, 0, '2017-07-10 16:17:17', 0),
(140, 'Central African Republic', 1, NULL, 'CF', 0, 0, '2017-07-10 16:17:17', 0),
(148, 'Chad', 1, NULL, 'TD', 0, 0, '2017-07-10 16:17:17', 0),
(152, 'Chile', 1, NULL, 'CL', 0, 0, '2017-07-10 16:17:17', 0),
(156, 'China', 1, NULL, 'CN', 0, 0, '2017-07-10 16:17:17', 0),
(170, 'Colombia', 1, NULL, 'CO', 0, 0, '2017-07-10 16:17:17', 0),
(174, 'Comoros', 1, NULL, 'KM', 0, 0, '2017-07-10 16:17:17', 0),
(178, 'Congo, Republic Of The', 1, NULL, 'CG', 0, 0, '2017-07-10 16:17:17', 0),
(188, 'Costa Rica', 1, NULL, 'CR', 0, 0, '2017-07-10 16:17:17', 0),
(192, 'Cuba', 1, NULL, 'CU', 0, 0, '2017-07-10 16:17:17', 0),
(196, 'Cyprus', 1, NULL, 'CY', 0, 0, '2017-07-10 16:17:17', 0),
(202, 'Czech Republic', 1, NULL, 'CZ', 0, 0, '2017-07-10 16:17:17', 0),
(204, 'Benin', 1, NULL, 'BJ', 0, 0, '2017-07-10 16:17:17', 0),
(208, 'Denmark', 1, NULL, 'DK', 0, 0, '2017-07-10 16:17:17', 0),
(212, 'Dominica', 1, NULL, 'DM', 0, 0, '2017-07-10 16:17:17', 0),
(214, 'Dominican Republic', 1, NULL, 'DO', 0, 0, '2017-07-10 16:17:17', 0),
(218, 'Ecuador', 1, NULL, 'EC', 0, 0, '2017-07-10 16:17:17', 0),
(220, 'Egypt', 1, NULL, 'EG', 0, 0, '2017-07-10 16:17:17', 0),
(222, 'El Salvador', 1, NULL, 'SV', 0, 0, '2017-07-10 16:17:17', 0),
(226, 'Equatorial Guinea', 1, NULL, 'GQ', 0, 0, '2017-07-10 16:17:17', 0),
(227, 'Eritrea', 1, NULL, 'ER', 0, 0, '2017-07-10 16:17:17', 0),
(228, 'Estonia', 1, NULL, 'EE', 0, 0, '2017-07-10 16:17:17', 0),
(230, 'Ethiopia', 1, NULL, 'ET', 0, 0, '2017-07-10 16:17:17', 0),
(242, 'Fiji', 1, NULL, 'FJ', 0, 0, '2017-07-10 16:17:17', 0),
(246, 'Finland', 1, NULL, 'FI', 0, 0, '2017-07-10 16:17:17', 0),
(250, 'France', 1, NULL, 'FR', 10, 1, '2018-12-20 09:39:15', 3303),
(262, 'Djibouti', 1, NULL, 'DJ', 0, 0, '2017-07-10 16:17:17', 0),
(266, 'Gabon', 1, NULL, 'GA', 0, 0, '2017-07-10 16:17:17', 0),
(270, 'Gambia', 1, NULL, 'GM', 0, 0, '2017-07-10 16:17:17', 0),
(272, 'Georgia', 1, NULL, 'GE', 0, 0, '2017-07-10 16:17:17', 0),
(280, 'Germany, Federal Republic Of', 1, NULL, 'DE', 5, 1, '2018-12-20 09:39:15', 3303),
(288, 'Ghana', 1, NULL, 'GH', 0, 0, '2017-07-10 16:17:17', 0),
(300, 'Greece', 1, NULL, 'GR', 0, 0, '2017-07-10 16:17:17', 0),
(308, 'Grenada', 1, NULL, 'GD', 0, 0, '2017-07-10 16:17:17', 0),
(320, 'Guatemala', 1, NULL, 'GT', 0, 0, '2017-07-10 16:17:17', 0),
(324, 'Guinea', 1, NULL, 'GN', 0, 0, '2017-07-10 16:17:17', 0),
(328, 'Guyana', 1, NULL, 'GY', 0, 0, '2017-07-10 16:17:17', 0),
(332, 'Haiti', 1, NULL, 'HT', 0, 0, '2017-07-10 16:17:17', 0),
(336, 'Vatican City State', 1, NULL, 'VA', 0, 0, '2017-07-10 16:17:17', 0),
(340, 'Honduras', 1, NULL, 'HN', 0, 0, '2017-07-10 16:17:17', 0),
(348, 'Hungary', 1, NULL, 'HU', 0, 0, '2017-07-10 16:17:17', 0),
(352, 'Iceland', 1, NULL, 'IS', 0, 0, '2017-07-10 16:17:17', 0),
(356, 'India', 1, NULL, 'IN', 0, 0, '2017-07-10 16:17:17', 0),
(360, 'Indonesia', 1, NULL, 'ID', 0, 0, '2017-07-10 16:17:17', 0),
(364, 'Iran (Islamic Rep. Of)', 1, NULL, 'IR', 0, 0, '2017-07-10 16:17:17', 0),
(368, 'Iraq', 1, NULL, 'IQ', 0, 0, '2017-07-10 16:17:17', 0),
(372, 'Ireland', 1, NULL, 'IE', 0, 1, '2018-12-20 09:39:15', 3303),
(376, 'Israel', 1, NULL, 'IL', 0, 0, '2017-07-10 16:17:17', 0),
(380, 'Italy', 1, NULL, 'IT', 0, 0, '2017-07-10 16:17:17', 0),
(388, 'Jamaica', 1, NULL, 'JM', 0, 0, '2017-07-10 16:17:17', 0),
(392, 'Japan', 1, NULL, 'JP', 0, 0, '2017-07-10 16:17:17', 0),
(400, 'Jordan', 1, NULL, 'JO', 0, 0, '2017-07-10 16:17:17', 0),
(402, 'Kazakhstan', 1, NULL, 'KZ', 0, 0, '2017-07-10 16:17:17', 0),
(404, 'Kenya', 1, NULL, 'KE', 0, 0, '2017-07-10 16:17:17', 0),
(406, 'Cambodia', 1, NULL, 'KH', 0, 0, '2017-07-10 16:17:17', 0),
(407, 'Korea South, Republic Of', 1, NULL, 'KR', 0, 0, '2017-07-10 16:17:17', 0),
(409, 'Korea North, Democratic Peoples Rep.', 1, NULL, 'KP', 0, 0, '2017-07-10 16:17:17', 0),
(414, 'Kuwait', 1, NULL, 'KW', 0, 0, '2017-07-10 16:17:17', 0),
(416, 'Kyrgyzstan', 1, NULL, 'KG', 0, 0, '2017-07-10 16:17:17', 0),
(418, 'Lao People\'s Democratic Rep.', 1, NULL, 'LA', 0, 0, '2017-07-10 16:17:17', 0),
(420, 'Latvia', 1, NULL, 'LV', 0, 0, '2017-07-10 16:17:17', 0),
(422, 'Lebanon', 1, NULL, 'LB', 0, 0, '2017-07-10 16:17:17', 0),
(426, 'Lesotho', 1, NULL, 'LS', 0, 0, '2017-07-10 16:17:17', 0),
(430, 'Liberia', 1, NULL, 'LR', 0, 0, '2017-07-10 16:17:17', 0),
(434, 'Libya', 1, NULL, 'LY', 0, 0, '2017-07-10 16:17:17', 0),
(438, 'Liechtenstein', 1, NULL, 'LI', 0, 0, '2017-07-10 16:17:17', 0),
(440, 'Lithuania', 1, NULL, 'LT', 0, 0, '2017-07-10 16:17:17', 0),
(442, 'Luxemburg', 1, NULL, 'LU', 0, 0, '2017-07-10 16:17:17', 0),
(448, 'Macedonia, Rep. Of', 1, NULL, 'MK', 0, 0, '2017-07-10 16:17:17', 0),
(450, 'Madagascar', 1, NULL, 'MG', 0, 0, '2017-07-10 16:17:17', 0),
(454, 'Malawi', 1, NULL, 'MW', 0, 0, '2017-07-10 16:17:17', 0),
(458, 'Malaysia', 1, NULL, 'MY', 0, 0, '2017-07-10 16:17:17', 0),
(462, 'Maldives', 1, NULL, 'MV', 0, 0, '2017-07-10 16:17:17', 0),
(466, 'Mali', 1, NULL, 'ML', 0, 0, '2017-07-10 16:17:17', 0),
(470, 'Malta', 1, NULL, 'MT', 0, 0, '2017-07-10 16:17:17', 0),
(478, 'Mauritania', 1, NULL, 'MR', 0, 0, '2017-07-10 16:17:17', 0),
(480, 'Mauritius', 1, NULL, 'MU', 0, 0, '2017-07-10 16:17:17', 0),
(484, 'Mexico', 1, NULL, 'MX', 0, 0, '2017-07-10 16:17:17', 0),
(492, 'Monaco', 1, NULL, 'MC', 0, 0, '2017-07-10 16:17:17', 0),
(496, 'Mongolia', 1, NULL, 'MN', 0, 0, '2017-07-10 16:17:17', 0),
(497, 'Montenegro', 1, NULL, 'ME', 0, 0, '2017-07-10 16:17:17', 0),
(504, 'Morocco', 1, NULL, 'MA', 0, 0, '2017-07-10 16:17:17', 0),
(508, 'Mozambique', 1, NULL, 'MZ', 0, 0, '2017-07-10 16:17:17', 0),
(516, 'Namibia', 1, NULL, 'NA', 0, 0, '2017-07-10 16:17:17', 0),
(524, 'Nepal', 1, NULL, 'NP', 0, 0, '2017-07-10 16:17:17', 0),
(528, 'Netherlands', 1, NULL, 'NL', 4, 1, '2018-12-20 09:39:15', 3303),
(554, 'New Zealand', 1, NULL, 'NZ', 0, 0, '2017-07-10 16:17:17', 0),
(558, 'Nicaragua', 1, NULL, 'NI', 0, 0, '2017-07-10 16:17:17', 0),
(562, 'Niger', 1, NULL, 'NE', 0, 0, '2017-07-10 16:17:17', 0),
(566, 'Nigeria', 1, NULL, 'NG', 11, 1, '2018-12-20 09:39:15', 3303),
(578, 'Norway', 1, NULL, 'NO', 0, 0, '2017-07-10 16:17:17', 0),
(580, 'Oman', 1, NULL, 'OM', 0, 0, '2017-07-10 16:17:17', 0),
(586, 'Pakistan', 1, NULL, 'PK', 0, 0, '2017-07-10 16:17:17', 0),
(588, 'Palestine', 1, NULL, 'PS', 0, 0, '2017-07-10 16:17:17', 0),
(590, 'Panama', 1, NULL, 'PA', 0, 0, '2017-07-10 16:17:17', 0),
(598, 'Papua New Guinea', 1, NULL, 'PG', 0, 0, '2017-07-10 16:17:17', 0),
(600, 'Paraguay', 1, NULL, 'PY', 0, 0, '2017-07-10 16:17:17', 0),
(604, 'Peru', 1, NULL, 'PE', 0, 0, '2017-07-10 16:17:17', 0),
(608, 'Philippines', 1, NULL, 'PH', 0, 0, '2017-07-10 16:17:17', 0),
(616, 'Poland', 1, NULL, 'PL', 7, 1, '2018-12-20 09:39:15', 3303),
(620, 'Portugal', 1, NULL, 'PT', 8, 1, '2018-12-20 09:39:15', 3303),
(622, 'Moldova, Republic Of', 1, NULL, 'MD', 0, 0, '2017-07-10 16:17:17', 0),
(624, 'Guinea-Bissau', 1, NULL, 'GW', 0, 0, '2017-07-10 16:17:17', 0),
(634, 'Qatar', 1, NULL, 'QA', 0, 0, '2017-07-10 16:17:17', 0),
(642, 'Romania', 1, NULL, 'RO', 0, 0, '2017-07-10 16:17:17', 0),
(643, 'Russian Federation', 1, NULL, 'RU', 0, 0, '2017-07-10 16:17:17', 0),
(646, 'Rwanda', 1, NULL, 'RW', 0, 0, '2017-07-10 16:17:17', 0),
(662, 'St. Lucia', 1, NULL, 'LC', 0, 0, '2017-07-10 16:17:17', 0),
(670, 'St. Vincent And The Grenadines', 1, NULL, 'VC', 0, 0, '2017-07-10 16:17:17', 0),
(672, 'Slovakia', 1, NULL, 'SK', 0, 0, '2017-07-10 16:17:17', 0),
(674, 'San Marino', 1, NULL, 'SM', 0, 0, '2017-07-10 16:17:17', 0),
(676, 'Slovenia', 1, NULL, 'SI', 0, 0, '2017-07-10 16:17:17', 0),
(682, 'Saudi Arabia', 1, NULL, 'SA', 0, 0, '2017-07-10 16:17:17', 0),
(686, 'Senegal', 1, NULL, 'SN', 0, 0, '2017-07-10 16:17:17', 0),
(687, 'Serbia', 1, NULL, 'RS', 0, 0, '2017-07-10 16:17:17', 0),
(690, 'Seychelles', 1, NULL, 'SC', 0, 0, '2017-07-10 16:17:17', 0),
(694, 'Sierra Leone', 1, NULL, 'SL', 0, 0, '2017-07-10 16:17:17', 0),
(702, 'Singapore', 1, NULL, 'SG', 0, 0, '2017-07-10 16:17:17', 0),
(706, 'Somalia', 1, NULL, 'SO', 0, 0, '2017-07-10 16:17:17', 0),
(710, 'South Africa', 1, NULL, 'ZA', 0, 0, '2017-07-10 16:17:17', 0),
(716, 'Zimbabwe', 1, NULL, 'ZW', 0, 0, '2017-07-10 16:17:17', 0),
(724, 'Spain', 1, NULL, 'ES', 9, 1, '2018-12-20 09:39:15', 3303),
(734, 'Sri Lanka', 1, NULL, 'LK', 0, 0, '2017-07-10 16:17:17', 0),
(736, 'Sudan', 1, NULL, 'SD', 0, 0, '2017-07-10 16:17:17', 0),
(740, 'Suriname', 1, NULL, 'SR', 0, 0, '2017-07-10 16:17:17', 0),
(748, 'Swaziland', 1, NULL, 'SZ', 0, 0, '2017-07-10 16:17:17', 0),
(752, 'Sweden', 1, NULL, 'SE', 0, 0, '2017-07-10 16:17:17', 0),
(756, 'Switzerland', 1, NULL, 'CH', 0, 0, '2017-07-10 16:17:17', 0),
(760, 'Syrian Arab Republic', 1, NULL, 'SY', 0, 0, '2017-07-10 16:17:17', 0),
(761, 'Tajikistan', 1, NULL, 'TJ', 0, 0, '2017-07-10 16:17:17', 0),
(762, 'Tanzania, United Republic Of', 1, NULL, 'TZ', 0, 0, '2017-07-10 16:17:17', 0),
(764, 'Thailand', 1, NULL, 'TH', 0, 0, '2017-07-10 16:17:17', 0),
(768, 'Togo', 1, NULL, 'TG', 0, 0, '2017-07-10 16:17:17', 0),
(776, 'Tonga', 1, NULL, 'TO', 0, 0, '2017-07-10 16:17:17', 0),
(780, 'Trinidad And Tobago', 1, NULL, 'TT', 0, 0, '2017-07-10 16:17:17', 0),
(788, 'Tunisia', 1, NULL, 'TN', 0, 0, '2017-07-10 16:17:17', 0),
(792, 'Turkey', 1, NULL, 'TR', 0, 0, '2017-07-10 16:17:17', 0),
(794, 'Turkmenistan', 1, NULL, 'TM', 0, 0, '2017-07-10 16:17:17', 0),
(800, 'Uganda', 1, NULL, 'UG', 0, 0, '2017-07-10 16:17:17', 0),
(805, 'Belarus', 1, NULL, 'BY', 0, 0, '2017-07-10 16:17:17', 0),
(807, 'Ukraine', 1, NULL, 'UA', 0, 0, '2017-07-10 16:17:17', 0),
(812, 'United Arab Emirates', 1, NULL, 'AE', 0, 0, '2017-07-10 16:17:17', 0),
(826, 'United Kingdom', 1, NULL, 'GB', 1, 1, '2018-12-20 09:39:15', 3303),
(840, 'United States Of America', 1, NULL, 'US', 0, 0, '2017-07-10 16:17:17', 0),
(854, 'Burkina-Faso', 1, NULL, 'BF', 0, 0, '2017-07-10 16:17:17', 0),
(858, 'Uruguay', 1, NULL, 'UY', 0, 0, '2017-07-10 16:17:17', 0),
(860, 'Uzbekistan', 1, NULL, 'UZ', 0, 0, '2017-07-10 16:17:17', 0),
(862, 'Venezuela', 1, NULL, 'VE', 0, 0, '2017-07-10 16:17:17', 0),
(868, 'Vietnam', 1, NULL, 'VN', 0, 0, '2017-07-10 16:17:17', 0),
(882, 'Samoa', 1, NULL, 'WS', 0, 0, '2017-07-10 16:17:17', 0),
(885, 'Yemen', 1, NULL, 'YE', 0, 0, '2017-07-10 16:17:17', 0),
(894, 'Zambia', 1, NULL, 'ZM', 0, 0, '2017-07-10 16:17:17', 0),
(966, 'Congo, Democratic Republic Of The', 1, NULL, 'CD', 0, 0, '2017-07-10 16:17:17', 0),
(967, 'Kiribati', 1, NULL, 'KI', 0, 0, '2017-07-10 16:17:17', 0),
(968, 'Kosovo', 1, NULL, 'XK', 0, 0, '2017-07-10 16:17:17', 0),
(969, 'Marshall Islands', 1, NULL, 'MH', 0, 0, '2017-07-10 16:17:17', 0),
(970, 'Micronesa, Federated States Of', 1, NULL, 'FM', 0, 0, '2017-07-10 16:17:17', 0),
(971, 'Nauru', 1, NULL, 'NR', 0, 0, '2017-07-10 16:17:17', 0),
(972, 'Palau', 1, NULL, 'PW', 0, 0, '2017-07-10 16:17:17', 0),
(973, 'Saint Kitts And Nevis', 1, NULL, 'KN', 0, 0, '2017-07-10 16:17:17', 0),
(974, 'South Sudan', 1, NULL, 'SS', 0, 0, '2017-07-10 16:17:17', 0),
(975, 'Taiwan', 1, NULL, 'TW', 0, 0, '2017-07-10 16:17:17', 0),
(976, 'Tuvalu', 1, NULL, 'TV', 0, 0, '2017-07-10 16:17:17', 0),
(977, 'Vanuatu', 1, NULL, 'VU', 0, 0, '2017-07-10 16:17:17', 0),
(998, 'Dual Nationality (Ireland And Other)', 1, NULL, NULL, 3, 1, '2018-12-20 09:39:15', 3303),
(999, 'Dual Nationality (both Non-Ireland)', 1, NULL, NULL, 2, 1, '2018-12-20 09:39:15', 3303);

-- --------------------------------------------------------

--
-- Table structure for table prm_county
--

CREATE TABLE prm_county (
  id int(10) UNSIGNED NOT NULL,
  title varchar(100) COLLATE utf8_bin NOT NULL,
  pod_restrained tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  parent_id int(10) UNSIGNED DEFAULT NULL,
  attribute tinytext CHARACTER SET latin1 COLLATE latin1_bin,
  weight tinyint(4) UNSIGNED NOT NULL DEFAULT '0',
  active tinyint(1) UNSIGNED NOT NULL DEFAULT '1',
  updated datetime NOT NULL,
  updated_by int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Dumping data for table prm_county
--

INSERT INTO prm_county (id, title, pod_restrained, parent_id, attribute, weight, active, updated, updated_by) VALUES
(1, 'Carlow', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(2, 'Dublin', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(6, 'Kildare', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(7, 'Kilkenny', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(8, 'Laois', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(9, 'Longford', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(10, 'Louth', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(11, 'Meath', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(12, 'Offaly', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(13, 'Westmeath', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(14, 'Wexford', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(15, 'Wicklow', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(16, 'Clare', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(17, 'Cork', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(19, 'Kerry', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(20, 'Limerick', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(22, 'Tipperary', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(24, 'Waterford', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(27, 'Galway', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(28, 'Leitrim', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(29, 'Mayo', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(30, 'Roscommon', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(31, 'Sligo', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(32, 'Cavan', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(33, 'Donegal', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(34, 'Monaghan', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(35, 'Antrim', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(36, 'Armagh', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(37, 'Derry', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(38, 'Down', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(39, 'Fermanagh', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(40, 'Tyrone', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(41, 'Other', 1, 372, 'Co.', 0, 1, '2016-08-05 03:33:03', 1),
(43, 'Aberdeen City', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(44, 'Aberdeenshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(45, 'Angus', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(46, 'Argyll and Bute', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(47, 'Avon', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(48, 'Bedfordshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(49, 'Berkshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(50, 'Blaenau Gwent', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(51, 'Borders', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(52, 'Bridgend', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(53, 'Bristol', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(54, 'Buckinghamshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(55, 'Caerphilly', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(56, 'Cambridgeshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(57, 'Cardiff', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(58, 'Carmarthenshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(59, 'Ceredigion', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(60, 'Channel Islands', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(61, 'Cheshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(62, 'Clackmannan', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(63, 'Cleveland', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(64, 'Conwy', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(65, 'Cornwall', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(66, 'Cumbria', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(67, 'Denbighshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(68, 'Derbyshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(69, 'Devon', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(70, 'Dorset', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(71, 'Dumfries and Galloway', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(72, 'Durham', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(73, 'East Ayrshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(74, 'East Dunbartonshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(75, 'East Lothian', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(76, 'East Renfrewshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(77, 'East Riding of Yorkshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(78, 'East Sussex', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(79, 'Edinburgh City', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(80, 'Essex', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(81, 'Falkirk', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(82, 'Fife', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(83, 'Flintshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(84, 'Glasgow (City of)', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(85, 'Gloucestershire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(86, 'Greater Manchester', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(87, 'Gwynedd', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(88, 'Hampshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(89, 'Herefordshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(90, 'Hertfordshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(91, 'Highland', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(92, 'Humberside', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(93, 'Inverclyde', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(94, 'Isle of Anglesey', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(95, 'Isle of Man', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(96, 'Isle of Wight', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(97, 'Isles of Scilly', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(98, 'Kent', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(99, 'Lancashire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(100, 'Leicestershire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(101, 'Lincolnshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(102, 'London', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(103, 'Merseyside', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(104, 'Merthyr Tydfil', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(105, 'Middlesex', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(106, 'Midlothian', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(107, 'Monmouthshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(108, 'Moray', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(109, 'Neath Port Talbot', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(110, 'Newport', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(111, 'Norfolk', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(112, 'North Ayrshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(113, 'North Lanarkshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(114, 'North Yorkshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(115, 'Northamptonshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(116, 'Northumberland', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(117, 'Nottinghamshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(118, 'Orkney', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(119, 'Oxfordshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(120, 'Pembrokeshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(121, 'Perthshire and Kinross', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(122, 'Powys', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(123, 'Renfrewshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(124, 'Rhondda Cynon Taff', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(125, 'Roxburghshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(126, 'Rutland', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(127, 'Shetland', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(128, 'Shropshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(129, 'Somerset', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(130, 'South Ayrshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(131, 'South Lanarkshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(132, 'South Yorkshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(133, 'Staffordshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(134, 'Sterling', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(135, 'Suffolk', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(136, 'Surrey', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(137, 'Swansea', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(138, 'The Vale of Glamorgan', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(139, 'Torfaen', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(140, 'Tyne and Wear', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(141, 'Warwickshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(142, 'West Dunbartonshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(143, 'West Lothian', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(144, 'West Midlands', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(145, 'West Sussex', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(146, 'West Yorkshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(147, 'Western Isles', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(148, 'Wiltshire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(149, 'Worcestershire', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1),
(150, 'Wrexham', 0, 826, 'Co.', 0, 0, '2016-08-05 03:33:03', 1);

-- --------------------------------------------------------

--
-- Table structure for table prm_salutation
--

CREATE TABLE prm_salutation (
  id int(10) UNSIGNED NOT NULL,
  title varchar(100) COLLATE utf8_bin NOT NULL,
  pod_restrained tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  parent_id int(10) UNSIGNED DEFAULT NULL,
  attribute tinytext CHARACTER SET latin1 COLLATE latin1_bin,
  weight tinyint(4) UNSIGNED NOT NULL DEFAULT '0',
  active tinyint(1) UNSIGNED NOT NULL DEFAULT '1',
  updated datetime NOT NULL,
  updated_by int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Dumping data for table prm_salutation
--

INSERT INTO prm_salutation (id, title, pod_restrained, parent_id, attribute, weight, active, updated, updated_by) VALUES
(1, 'Ath.', 1, NULL, NULL, 3, 1, '2016-08-05 03:33:03', 1),
(2, 'Br.', 1, NULL, NULL, 3, 1, '2016-08-05 03:33:03', 1),
(3, 'Dr.', 1, NULL, NULL, 3, 1, '2016-08-05 03:33:03', 1),
(4, 'Fr.', 1, NULL, NULL, 3, 1, '2016-08-05 03:33:03', 1),
(5, 'Miss', 1, NULL, NULL, 2, 1, '2016-08-05 03:33:03', 1),
(6, 'Mr.', 1, NULL, NULL, 0, 1, '2016-08-05 03:33:03', 1),
(7, 'Mrs.', 1, NULL, NULL, 1, 1, '2016-08-05 03:33:03', 1),
(8, 'Ms.', 1, NULL, NULL, 2, 1, '2016-08-05 03:33:03', 1),
(9, 'Rev.', 1, NULL, NULL, 3, 1, '2016-08-05 03:33:03', 1),
(10, 'Senator', 1, NULL, NULL, 4, 1, '2016-08-05 03:33:03', 1),
(11, 'Sr.', 1, NULL, NULL, 3, 1, '2016-08-05 03:33:03', 1),
(12, 'Uas.', 1, NULL, NULL, 3, 1, '2016-08-05 03:33:03', 1),
(13, 'Most Reverend', 1, NULL, NULL, 4, 1, '2016-08-05 03:33:03', 1),
(14, 'The Right Rev.', 1, NULL, NULL, 4, 1, '2016-08-05 03:33:03', 1),
(15, 'Very Rev.', 1, NULL, NULL, 4, 1, '2016-08-05 03:33:03', 1),
(16, 'Canon', 1, NULL, NULL, 4, 1, '2016-08-05 03:33:03', 1),
(17, 'Mons.', 1, NULL, NULL, 4, 1, '2016-08-05 03:33:03', 1),
(18, 'Prof.', 1, NULL, NULL, 4, 1, '2016-08-05 03:33:03', 1),
(19, 'The', 1, NULL, NULL, 4, 1, '2016-08-05 03:33:03', 1);

-- --------------------------------------------------------

--
-- Table structure for table prm_staff_role
--

CREATE TABLE prm_staff_role (
  id int(10) UNSIGNED NOT NULL,
  title varchar(100) COLLATE utf8_bin NOT NULL,
  pod_restrained tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  parent_id int(10) UNSIGNED DEFAULT NULL,
  attribute tinytext CHARACTER SET latin1 COLLATE latin1_bin,
  weight tinyint(4) UNSIGNED NOT NULL DEFAULT '0',
  active tinyint(1) UNSIGNED NOT NULL DEFAULT '1',
  updated datetime NOT NULL,
  updated_by int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Dumping data for table prm_staff_role
--

INSERT INTO prm_staff_role (id, title, pod_restrained, parent_id, attribute, weight, active, updated, updated_by) VALUES
(1, 'Developer', 0, 1, '1', 0, 1, '2016-07-25 00:00:00', 1),
(2, 'Administrator', 0, 2, '11', 0, 1, '2016-07-25 00:00:00', 1),
(3, 'Group Leader', 0, 3, '21', 0, 1, '2016-07-25 00:00:00', 1),
(4, 'Group Assistant', 0, 4, '25', 0, 1, '2016-07-25 00:00:00', 1),
(5, 'Member Assistant', 0, 5, '27', 0, 1, '2016-07-25 00:00:00', 1),
(6, 'General Staff', 0, 6, '28', 0, 1, '2016-07-25 00:00:00', 1),
(7, 'Member', 0, 7, '45', 0, 1, '2016-07-25 00:00:00', 1),
(8, 'Guardian', 0, 8, '41', 0, 1, '2016-07-25 00:00:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table staff_role
--

CREATE TABLE staff_role (
  tbl_id tinyint(3) UNSIGNED NOT NULL,
  id int(10) UNSIGNED NOT NULL,
  role_id tinyint(3) UNSIGNED NOT NULL,
  role_begin date NOT NULL,
  role_end date DEFAULT NULL,
  updated datetime NOT NULL,
  updated_by smallint(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indexes for table app_screen_user
--
ALTER TABLE app_screen_user
  ADD PRIMARY KEY (tbl_id),
  ADD UNIQUE KEY id_app_module_model (id,role_id,app_slug(15),module(35),model(35)) USING BTREE,
  ADD KEY ind_id (id),
  ADD KEY app_slug (app_slug(15)) USING BTREE;

--
-- Indexes for table calendar_open_date
--
ALTER TABLE calendar_open_date
  ADD PRIMARY KEY (tbl_id),
  ADD KEY year_starting (year_starting);

--
-- Indexes for table calendar_term
--
ALTER TABLE calendar_term
  ADD PRIMARY KEY (id);

--
-- Indexes for table dashboard_notification
--
ALTER TABLE dashboard_notification
  ADD PRIMARY KEY (id),
  ADD KEY status (status(15));

--
-- Indexes for table dashboard_notification_user
--
ALTER TABLE dashboard_notification_user
  ADD PRIMARY KEY (tbl_id),
  ADD UNIQUE KEY id_2 (id,role_id,notification_id),
  ADD KEY id (id),
  ADD KEY notification_id (notification_id),
  ADD KEY role_id (role_id);

--
-- Indexes for table email_account
--
ALTER TABLE email_account
  ADD PRIMARY KEY (id);

--
-- Indexes for table email_template
--
ALTER TABLE email_template
  ADD PRIMARY KEY (id);

--
-- Indexes for table group_class
--
ALTER TABLE group_class
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY abbr (abbr) USING BTREE,
  ADD UNIQUE KEY title (title),
  ADD UNIQUE KEY ext_id (ext_id) USING BTREE,
  ADD KEY active (active),
  ADD KEY type_id (type_id),
  ADD KEY supergroup_id (group_super_id) USING BTREE;

--
-- Indexes for table group_class_leader
--
ALTER TABLE group_class_leader
  ADD PRIMARY KEY (tbl_id),
  ADD KEY id (id),
  ADD KEY group_id (group_class_id),
  ADD KEY id_group (id,group_class_id) USING BTREE,
  ADD KEY group_super_id (group_super_id);

--
-- Indexes for table group_custom
--
ALTER TABLE group_custom
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY abbr (abbr) USING BTREE,
  ADD UNIQUE KEY title (title),
  ADD UNIQUE KEY ext_id (ext_id) USING BTREE,
  ADD KEY active (active);

--
-- Indexes for table group_custom_leader
--
ALTER TABLE group_custom_leader
  ADD PRIMARY KEY (tbl_id),
  ADD KEY id (id),
  ADD KEY group_id (group_custom_id),
  ADD KEY id_group (id,group_custom_id) USING BTREE;

--
-- Indexes for table group_staff
--
ALTER TABLE group_staff
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY abbr (abbr) USING BTREE,
  ADD UNIQUE KEY title (title),
  ADD UNIQUE KEY ext_id (ext_id) USING BTREE,
  ADD KEY active (active);

--
-- Indexes for table group_staff_staff
--
ALTER TABLE group_staff_staff
  ADD PRIMARY KEY (tbl_id),
  ADD KEY id (id),
  ADD KEY group_id (group_staff_id),
  ADD KEY id_group (id,group_staff_id) USING BTREE;

--
-- Indexes for table group_super
--
ALTER TABLE group_super
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY title (title) USING BTREE,
  ADD UNIQUE KEY abbr (abbr) USING BTREE,
  ADD UNIQUE KEY ext_id (ext_id) USING BTREE,
  ADD KEY active (active),
  ADD KEY current (current);

--
-- Indexes for table manager_setting
--
ALTER TABLE manager_setting
  ADD PRIMARY KEY (tbl_id),
  ADD UNIQUE KEY setting_key (setting_key(35),setting_year_starting);

--
-- Indexes for table member_group_assistant
--
ALTER TABLE member_group_assistant
  ADD PRIMARY KEY (tbl_id),
  ADD UNIQUE KEY id_group_end (id,group_assistant_id,assistant_end),
  ADD KEY id (id),
  ADD KEY group_id (group_assistant_id),
  ADD KEY id_group (id,group_assistant_id) USING BTREE;

--
-- Indexes for table member_group_class
--
ALTER TABLE member_group_class
  ADD PRIMARY KEY (tbl_id),
  ADD UNIQUE KEY id_group_end (id,group_class_id,in_group_end),
  ADD KEY id (id),
  ADD KEY group_id (group_class_id),
  ADD KEY id_group (id,group_class_id) USING BTREE,
  ADD KEY group_super_id (group_super_id);

--
-- Indexes for table member_group_custom
--
ALTER TABLE member_group_custom
  ADD PRIMARY KEY (tbl_id),
  ADD UNIQUE KEY id_group_end (id,group_custom_id,in_group_end),
  ADD KEY id (id),
  ADD KEY group_id (group_custom_id),
  ADD KEY id_group (id,group_custom_id) USING BTREE;

--
-- Indexes for table member_guardian
--
ALTER TABLE member_guardian
  ADD PRIMARY KEY (tbl_id),
  ADD KEY guardian_end (guardian_end) USING BTREE,
  ADD KEY guardian_id (guardian_id),
  ADD KEY id (id),
  ADD KEY is_default (is_default),
  ADD KEY include_in_email (include_in_email),
  ADD KEY include_in_text (include_in_text),
  ADD KEY include_in_letter (include_in_letter);

--
-- Indexes for table member_member_assistant
--
ALTER TABLE member_member_assistant
  ADD PRIMARY KEY (tbl_id),
  ADD UNIQUE KEY id_group_end (id,member_assistant_id,assistant_end),
  ADD KEY id (id),
  ADD KEY group_id (member_assistant_id),
  ADD KEY id_group (id,member_assistant_id) USING BTREE;

--
-- Indexes for table member_sibling
--
ALTER TABLE member_sibling
  ADD PRIMARY KEY (tbl_id),
  ADD KEY sibling_end (sibling_end),
  ADD KEY sibling_id (sibling_id),
  ADD KEY id (id);

--
-- Indexes for table person
--
ALTER TABLE person
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY email (email(50)),
  ADD UNIQUE KEY mobile (mobile(20)) USING BTREE,
  ADD UNIQUE KEY mobile_alt (mobile_alt(20)),
  ADD UNIQUE KEY email_alt (email_alt(50)),
  ADD KEY active (active),
  ADD KEY lname_index (indexed_lname(35)) USING BTREE,
  ADD KEY is_staff (is_staff),
  ADD KEY is_guardian (is_guardian),
  ADD KEY is_member (is_member),
  ADD KEY county_id (county_id),
  ADD KEY country_id (country_id),
  ADD KEY ext_id (ext_id(5)) USING BTREE;

--
-- Indexes for table prm_country
--
ALTER TABLE prm_country
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY title (title),
  ADD KEY active (active),
  ADD KEY weight (weight);

--
-- Indexes for table prm_county
--
ALTER TABLE prm_county
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY title (title),
  ADD KEY active (active),
  ADD KEY weight (weight),
  ADD KEY parent_id (parent_id);

--
-- Indexes for table prm_salutation
--
ALTER TABLE prm_salutation
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY title (title),
  ADD KEY weight (weight),
  ADD KEY active (active);

--
-- Indexes for table prm_staff_role
--
ALTER TABLE prm_staff_role
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY title (title),
  ADD KEY active (active),
  ADD KEY weight (weight);

--
-- Indexes for table staff_role
--
ALTER TABLE staff_role
  ADD PRIMARY KEY (tbl_id),
  ADD UNIQUE KEY id_role (id,role_id),
  ADD KEY id (id),
  ADD KEY role (role_id);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table app_screen_user
--
ALTER TABLE app_screen_user
  MODIFY tbl_id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table calendar_open_date
--
ALTER TABLE calendar_open_date
  MODIFY tbl_id int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table calendar_term
--
ALTER TABLE calendar_term
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table dashboard_notification
--
ALTER TABLE dashboard_notification
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table dashboard_notification_user
--
ALTER TABLE dashboard_notification_user
  MODIFY tbl_id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table email_account
--
ALTER TABLE email_account
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table email_template
--
ALTER TABLE email_template
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table group_class
--
ALTER TABLE group_class
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table group_class_leader
--
ALTER TABLE group_class_leader
  MODIFY tbl_id int(6) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table group_custom
--
ALTER TABLE group_custom
  MODIFY id tinyint(2) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table group_custom_leader
--
ALTER TABLE group_custom_leader
  MODIFY tbl_id int(6) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table group_staff
--
ALTER TABLE group_staff
  MODIFY id tinyint(2) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table group_staff_staff
--
ALTER TABLE group_staff_staff
  MODIFY tbl_id int(6) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table group_super
--
ALTER TABLE group_super
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table manager_setting
--
ALTER TABLE manager_setting
  MODIFY tbl_id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table member_group_assistant
--
ALTER TABLE member_group_assistant
  MODIFY tbl_id int(6) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table member_group_class
--
ALTER TABLE member_group_class
  MODIFY tbl_id int(6) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table member_group_custom
--
ALTER TABLE member_group_custom
  MODIFY tbl_id int(6) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table member_guardian
--
ALTER TABLE member_guardian
  MODIFY tbl_id int(6) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table member_member_assistant
--
ALTER TABLE member_member_assistant
  MODIFY tbl_id int(6) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table member_sibling
--
ALTER TABLE member_sibling
  MODIFY tbl_id int(6) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table prm_county
--
ALTER TABLE prm_county
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=151;

--
-- AUTO_INCREMENT for table prm_salutation
--
ALTER TABLE prm_salutation
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table prm_staff_role
--
ALTER TABLE prm_staff_role
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table staff_role
--
ALTER TABLE staff_role
  MODIFY tbl_id tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table dashboard_notification_user
--
ALTER TABLE dashboard_notification_user
  ADD CONSTRAINT dashboard_notification_user_ibfk_2 FOREIGN KEY (notification_id) REFERENCES dashboard_notification (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT dashboard_notification_user_ibfk_3 FOREIGN KEY (id) REFERENCES person (id) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table group_class
--
ALTER TABLE group_class
  ADD CONSTRAINT group_class_ibfk_1 FOREIGN KEY (group_super_id) REFERENCES group_super (id) ON UPDATE NO ACTION;

--
-- Constraints for table group_class_leader
--
ALTER TABLE group_class_leader
  ADD CONSTRAINT group_class_leader_ibfk_1 FOREIGN KEY (id) REFERENCES person (id) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table group_custom_leader
--
ALTER TABLE group_custom_leader
  ADD CONSTRAINT group_custom_leader_ibfk_1 FOREIGN KEY (id) REFERENCES person (id) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table group_staff_staff
--
ALTER TABLE group_staff_staff
  ADD CONSTRAINT group_staff_staff_ibfk_1 FOREIGN KEY (id) REFERENCES person (id) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table member_group_assistant
--
ALTER TABLE member_group_assistant
  ADD CONSTRAINT member_group_assistant_ibfk_1 FOREIGN KEY (id) REFERENCES person (id) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT member_group_assistant_ibfk_2 FOREIGN KEY (group_assistant_id) REFERENCES person (id) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table member_group_class
--
ALTER TABLE member_group_class
  ADD CONSTRAINT member_group_class_ibfk_1 FOREIGN KEY (group_class_id) REFERENCES group_class (id) ON UPDATE NO ACTION,
  ADD CONSTRAINT member_group_class_ibfk_2 FOREIGN KEY (group_super_id) REFERENCES group_super (id) ON UPDATE NO ACTION,
  ADD CONSTRAINT member_group_class_ibfk_3 FOREIGN KEY (id) REFERENCES person (id) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table member_group_custom
--
ALTER TABLE member_group_custom
  ADD CONSTRAINT member_group_custom_ibfk_1 FOREIGN KEY (id) REFERENCES person (id) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT member_group_custom_ibfk_2 FOREIGN KEY (group_custom_id) REFERENCES group_custom (id) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table member_guardian
--
ALTER TABLE member_guardian
  ADD CONSTRAINT member_guardian_ibfk_1 FOREIGN KEY (id) REFERENCES person (id) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT member_guardian_ibfk_2 FOREIGN KEY (guardian_id) REFERENCES person (id) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table member_member_assistant
--
ALTER TABLE member_member_assistant
  ADD CONSTRAINT member_member_assistant_ibfk_1 FOREIGN KEY (id) REFERENCES person (id) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT member_member_assistant_ibfk_2 FOREIGN KEY (member_assistant_id) REFERENCES person (id) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table member_sibling
--
ALTER TABLE member_sibling
  ADD CONSTRAINT member_sibling_ibfk_1 FOREIGN KEY (id) REFERENCES person (id) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT member_sibling_ibfk_2 FOREIGN KEY (sibling_id) REFERENCES person (id) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table person
--
ALTER TABLE person
  ADD CONSTRAINT person_ibfk_1 FOREIGN KEY (county_id) REFERENCES prm_county (id) ON DELETE SET NULL ON UPDATE NO ACTION,
  ADD CONSTRAINT person_ibfk_2 FOREIGN KEY (country_id) REFERENCES prm_country (id) ON DELETE SET NULL ON UPDATE NO ACTION;

--
-- Constraints for table staff_role
--
ALTER TABLE staff_role
  ADD CONSTRAINT staff_role_ibfk_1 FOREIGN KEY (id) REFERENCES person (id) ON DELETE CASCADE ON UPDATE NO ACTION;