
USE $_ORG_DATABASE_NAME;

-- --------------------------------------------------------

--
-- Table structure for table tp_period_cm
--

CREATE TABLE tp_period_cm (
  id int(10) UNSIGNED NOT NULL,
  year_starting year(4) NOT NULL,
  period_lt_id int(10) UNSIGNED NOT NULL,
  term_id int(10) UNSIGNED NOT NULL,
  period_start date NOT NULL,
  period_end date NOT NULL,
  updated datetime NOT NULL,
  updated_by int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table tp_period_lt
--

CREATE TABLE tp_period_lt (
  id int(10) UNSIGNED NOT NULL,
  year_starting year(4) NOT NULL,
  title tinytext NOT NULL,
  period_start date DEFAULT NULL,
  period_end date DEFAULT NULL,
  active tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  updated datetime NOT NULL,
  updated_by int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table tp_period_lt_term
--

CREATE TABLE tp_period_lt_term (
  tbl_id int(10) UNSIGNED NOT NULL,
  year_starting year(4) NOT NULL,
  period_id int(10) UNSIGNED NOT NULL,
  term_id int(10) UNSIGNED DEFAULT NULL,
  updated datetime NOT NULL,
  updated_by int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table tp_period_st
--

CREATE TABLE tp_period_st (
  id int(10) UNSIGNED NOT NULL,
  staff_id int(5) UNSIGNED NOT NULL,
  year_starting year(4) NOT NULL,
  period_lt_id int(5) UNSIGNED NOT NULL,
  period_cm_id int(10) UNSIGNED NOT NULL,
  period_start date NOT NULL,
  period_end date NOT NULL,
  updated datetime NOT NULL,
  updated_by int(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table tp_plan_lt
--

CREATE TABLE tp_plan_lt (
  id int(10) NOT NULL,
  staff_id int(5) UNSIGNED NOT NULL,
  year_starting year(4) NOT NULL,
  period_lt_id int(5) UNSIGNED DEFAULT NULL,
  subject_id int(11) NOT NULL,
  group_id tinyint(2) UNSIGNED NOT NULL,
  standard_id tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  title varchar(35) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  content longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  is_shared tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  export_id int(10) UNSIGNED NOT NULL DEFAULT '0',
  updated datetime NOT NULL,
  updated_by int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table tp_plan_st
--

CREATE TABLE tp_plan_st (
  id int(10) NOT NULL,
  staff_id int(10) UNSIGNED NOT NULL,
  year_starting year(4) NOT NULL,
  period_lt_id int(10) UNSIGNED DEFAULT NULL,
  period_st_id int(10) UNSIGNED DEFAULT NULL,
  language_token tinytext CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  export_id int(10) UNSIGNED NOT NULL DEFAULT '0',
  title varchar(500) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  footer varchar(500) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
  is_committed tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  updated datetime NOT NULL,
  updated_by int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table tp_plan_st_segment
--

CREATE TABLE tp_plan_st_segment (
  id int(10) UNSIGNED NOT NULL,
  parent_id int(10) UNSIGNED NOT NULL DEFAULT '0',
  plan_id int(10) NOT NULL,
  subject_id tinyint(2) UNSIGNED NOT NULL,
  standard_id int(10) UNSIGNED NOT NULL DEFAULT '0',
  group_id tinyint(2) UNSIGNED NOT NULL DEFAULT '0',
  strand_id int(10) UNSIGNED NOT NULL DEFAULT '0',
  unit_id int(10) UNSIGNED NOT NULL DEFAULT '0',
  objective_id int(10) UNSIGNED NOT NULL DEFAULT '0',
  note_id int(10) UNSIGNED NOT NULL DEFAULT '0',
  is_group tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  is_strand tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  is_unit tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  is_objective tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  is_note tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  is_learning_objective tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  content varchar(500) COLLATE utf8_bin DEFAULT NULL,
  document_order tinyint(3) UNSIGNED NOT NULL,
  is_done tinyint(1) UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- --------------------------------------------------------

--
-- Table structure for table tp_plan_st_segment_supplement
--

CREATE TABLE tp_plan_st_segment_supplement (
  id int(10) UNSIGNED NOT NULL,
  segment_id int(10) UNSIGNED NOT NULL,
  slug tinytext CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  content varchar(1000) COLLATE utf8_bin DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- --------------------------------------------------------

--
-- Table structure for table tp_plan_st_sort
--

CREATE TABLE tp_plan_st_sort (
  tbl_id int(10) UNSIGNED NOT NULL,
  staff_id int(11) UNSIGNED NOT NULL,
  period_st_id int(10) UNSIGNED DEFAULT NULL,
  language_token tinytext CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  group_id tinyint(2) UNSIGNED NOT NULL,
  plan_id int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table tp_policy_school
--

CREATE TABLE tp_policy_school (
  id int(11) NOT NULL,
  title varchar(35) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  content longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  export_id int(10) UNSIGNED NOT NULL DEFAULT '0',
  updated datetime NOT NULL,
  updated_by int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table tp_policy_subject
--

CREATE TABLE tp_policy_subject (
  id int(11) NOT NULL,
  export_id int(10) UNSIGNED NOT NULL,
  subject_id tinyint(2) UNSIGNED NOT NULL,
  title varchar(155) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  content longtext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  updated datetime NOT NULL,
  updated_by int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table tp_setting
--

CREATE TABLE tp_setting (
  tbl_id int(10) UNSIGNED NOT NULL,
  setting_key tinytext NOT NULL,
  setting_value varchar(50) DEFAULT NULL,
  setting_year_starting year(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table tp_period_cm
--
ALTER TABLE tp_period_cm
  ADD PRIMARY KEY (id),
  ADD KEY year_starting (year_starting),
  ADD KEY ltp_id (term_id),
  ADD KEY lt_period_id (period_lt_id);

--
-- Indexes for table tp_period_lt
--
ALTER TABLE tp_period_lt
  ADD PRIMARY KEY (id),
  ADD KEY active (active);

--
-- Indexes for table tp_period_lt_term
--
ALTER TABLE tp_period_lt_term
  ADD PRIMARY KEY (tbl_id),
  ADD UNIQUE KEY period_id_2 (period_id,term_id),
  ADD KEY period_id (period_id),
  ADD KEY term_id (term_id);

--
-- Indexes for table tp_period_st
--
ALTER TABLE tp_period_st
  ADD PRIMARY KEY (id),
  ADD KEY ltp_id (period_lt_id),
  ADD KEY year_starting (year_starting),
  ADD KEY user_id (staff_id),
  ADD KEY period_cm_id (period_cm_id);

--
-- Indexes for table tp_plan_lt
--
ALTER TABLE tp_plan_lt
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY user_year_ltp_subject (staff_id,year_starting,period_lt_id,subject_id),
  ADD KEY ltp_id (period_lt_id);

--
-- Indexes for table tp_plan_st
--
ALTER TABLE tp_plan_st
  ADD PRIMARY KEY (id),
  ADD KEY staff_id (staff_id),
  ADD KEY year_starting (year_starting),
  ADD KEY period_lt_id (period_lt_id),
  ADD KEY period_st_id (period_st_id);

--
-- Indexes for table tp_plan_st_segment
--
ALTER TABLE tp_plan_st_segment
  ADD PRIMARY KEY (id),
  ADD KEY plan_id (plan_id),
  ADD KEY parent_id (parent_id);

--
-- Indexes for table tp_plan_st_segment_supplement
--
ALTER TABLE tp_plan_st_segment_supplement
  ADD PRIMARY KEY (id),
  ADD KEY segment_id (segment_id);

--
-- Indexes for table tp_plan_st_sort
--
ALTER TABLE tp_plan_st_sort
  ADD PRIMARY KEY (tbl_id),
  ADD KEY group_id (group_id),
  ADD KEY period_st_id (period_st_id),
  ADD KEY plan_id (plan_id);

--
-- Indexes for table tp_policy_school
--
ALTER TABLE tp_policy_school
  ADD PRIMARY KEY (id);

--
-- Indexes for table tp_policy_subject
--
ALTER TABLE tp_policy_subject
  ADD PRIMARY KEY (id),
  ADD KEY export_id (export_id);

--
-- Indexes for table tp_setting
--
ALTER TABLE tp_setting
  ADD PRIMARY KEY (tbl_id),
  ADD UNIQUE KEY setting_key (setting_key(35),setting_year_starting);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table tp_period_cm
--
ALTER TABLE tp_period_cm
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;
--
-- AUTO_INCREMENT for table tp_period_lt
--
ALTER TABLE tp_period_lt
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
--
-- AUTO_INCREMENT for table tp_period_lt_term
--
ALTER TABLE tp_period_lt_term
  MODIFY tbl_id int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
--
-- AUTO_INCREMENT for table tp_period_st
--
ALTER TABLE tp_period_st
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;
--
-- AUTO_INCREMENT for table tp_plan_lt
--
ALTER TABLE tp_plan_lt
  MODIFY id int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
--
-- AUTO_INCREMENT for table tp_plan_st
--
ALTER TABLE tp_plan_st
  MODIFY id int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;
--
-- AUTO_INCREMENT for table tp_plan_st_segment
--
ALTER TABLE tp_plan_st_segment
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1384;
--
-- AUTO_INCREMENT for table tp_plan_st_segment_supplement
--
ALTER TABLE tp_plan_st_segment_supplement
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=967;
--
-- AUTO_INCREMENT for table tp_plan_st_sort
--
ALTER TABLE tp_plan_st_sort
  MODIFY tbl_id int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;
--
-- AUTO_INCREMENT for table tp_policy_school
--
ALTER TABLE tp_policy_school
  MODIFY id int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
--
-- AUTO_INCREMENT for table tp_policy_subject
--
ALTER TABLE tp_policy_subject
  MODIFY id int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;
--
-- AUTO_INCREMENT for table tp_setting
--
ALTER TABLE tp_setting
  MODIFY tbl_id int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
--
-- Constraints for dumped tables
--

--
-- Constraints for table tp_period_lt_term
--
ALTER TABLE tp_period_lt_term
  ADD CONSTRAINT tp_period_lt_term_ibfk_1 FOREIGN KEY (term_id) REFERENCES calendar_term (id) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table tp_period_st
--
ALTER TABLE tp_period_st
  ADD CONSTRAINT stp_ltp FOREIGN KEY (period_lt_id) REFERENCES tp_period_lt (id) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table tp_plan_st_segment
--
ALTER TABLE tp_plan_st_segment
  ADD CONSTRAINT tp_plan_st_segment_ibfk_1 FOREIGN KEY (plan_id) REFERENCES tp_plan_st (id) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table tp_plan_st_segment_supplement
--
ALTER TABLE tp_plan_st_segment_supplement
  ADD CONSTRAINT tp_plan_st_segment_supplement_ibfk_1 FOREIGN KEY (segment_id) REFERENCES tp_plan_st_segment (id) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table tp_plan_st_sort
--
ALTER TABLE tp_plan_st_sort
  ADD CONSTRAINT tp_plan_st_sort_ibfk_1 FOREIGN KEY (plan_id) REFERENCES tp_plan_st (id) ON DELETE CASCADE ON UPDATE NO ACTION;
SET FOREIGN_KEY_CHECKS=1;
