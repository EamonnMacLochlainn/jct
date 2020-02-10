
USE $_ORG_DATABASE_NAME;

-- --------------------------------------------------------

--
-- Table structure for table pm_day
--

CREATE TABLE pm_day (
  id smallint(5) UNSIGNED NOT NULL,
  event_id smallint(5) UNSIGNED NOT NULL,
  day_date date NOT NULL,
  day_start time NOT NULL,
  day_end time NOT NULL,
  venue varchar(35) NOT NULL,
  slot_mins tinyint(2) UNSIGNED NOT NULL,
  num_slots tinyint(2) UNSIGNED NOT NULL DEFAULT '1',
  max_meetings_per_slot tinyint(3) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table pm_event
--

CREATE TABLE pm_event (
  id smallint(5) UNSIGNED NOT NULL,
  year_starting year(4) NOT NULL,
  title varchar(50) NOT NULL,
  staff_option tinytext CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  show_to_staff tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  allow_reservation tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  active tinyint(1) UNSIGNED NOT NULL DEFAULT '1',
  updated datetime NOT NULL,
  updated_by int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table pm_event_setting
--

CREATE TABLE pm_event_setting (
  tbl_id int(10) UNSIGNED NOT NULL,
  event_id smallint(5) UNSIGNED NOT NULL,
  setting_key tinytext CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
  setting_value tinytext CHARACTER SET latin1 COLLATE latin1_bin,
  updated datetime NOT NULL,
  updated_by int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table pm_free_time
--

CREATE TABLE pm_free_time (
  tbl_id int(10) UNSIGNED NOT NULL,
  event_id smallint(5) UNSIGNED NOT NULL,
  day_id smallint(5) UNSIGNED NOT NULL,
  time_start time NOT NULL,
  time_end time NOT NULL,
  updated datetime NOT NULL,
  updated_by int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table pm_pairing
--

CREATE TABLE pm_pairing (
  id int(10) UNSIGNED NOT NULL,
  event_id smallint(5) UNSIGNED NOT NULL,
  member_id int(10) UNSIGNED NOT NULL,
  updated datetime NOT NULL,
  updated_by int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table pm_pairing_staff
--

CREATE TABLE pm_pairing_staff (
  tbl_id int(10) UNSIGNED NOT NULL,
  event_id smallint(5) UNSIGNED NOT NULL,
  pairing_id int(10) UNSIGNED NOT NULL,
  staff_id int(10) UNSIGNED NOT NULL,
  notification_sent tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  discarded tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  invalid tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  updated datetime NOT NULL,
  updated_by int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table pm_reservation
--

CREATE TABLE pm_reservation (
  id int(10) UNSIGNED NOT NULL,
  event_id smallint(5) UNSIGNED NOT NULL,
  day_id smallint(5) UNSIGNED NOT NULL,
  slot_id smallint(5) UNSIGNED NOT NULL,
  pairing_id int(10) UNSIGNED NOT NULL,
  is_locked tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  invitation_email_sent datetime DEFAULT NULL,
  invitation_text_sent datetime DEFAULT NULL,
  notification_email_sent datetime DEFAULT NULL,
  notification_text_sent datetime DEFAULT NULL,
  phase_token tinytext CHARACTER SET latin1 COLLATE latin1_bin,
  created datetime NOT NULL,
  created_by int(11) UNSIGNED NOT NULL,
  updated datetime NOT NULL,
  updated_by int(11) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table pm_setting
--

CREATE TABLE pm_setting (
  tbl_id int(10) UNSIGNED NOT NULL,
  setting_key tinytext NOT NULL,
  setting_value varchar(50) DEFAULT NULL,
  setting_year_starting year(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table pm_slot
--

CREATE TABLE pm_slot (
  id smallint(5) UNSIGNED NOT NULL,
  event_id smallint(5) UNSIGNED NOT NULL,
  day_id smallint(5) UNSIGNED NOT NULL,
  slot_start time NOT NULL,
  slot_end time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table pm_staff_break
--

CREATE TABLE pm_staff_break (
  tbl_id int(11) UNSIGNED NOT NULL,
  staff_id int(10) UNSIGNED NOT NULL,
  event_id smallint(5) UNSIGNED NOT NULL,
  day_id smallint(5) UNSIGNED NOT NULL,
  slot_id smallint(5) UNSIGNED NOT NULL,
  updated datetime NOT NULL,
  updated_by int(11) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table pm_day
--
ALTER TABLE pm_day
  ADD PRIMARY KEY (id),
  ADD KEY event_id (event_id);

--
-- Indexes for table pm_event
--
ALTER TABLE pm_event
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY title (title),
  ADD KEY year_starting (year_starting),
  ADD KEY active (active);

--
-- Indexes for table pm_event_setting
--
ALTER TABLE pm_event_setting
  ADD PRIMARY KEY (tbl_id),
  ADD KEY event_id (event_id);

--
-- Indexes for table pm_free_time
--
ALTER TABLE pm_free_time
  ADD PRIMARY KEY (tbl_id),
  ADD KEY event_id (event_id),
  ADD KEY day_id (day_id);

--
-- Indexes for table pm_pairing
--
ALTER TABLE pm_pairing
  ADD PRIMARY KEY (id),
  ADD KEY member_id (member_id),
  ADD KEY event_id (event_id);

--
-- Indexes for table pm_pairing_staff
--
ALTER TABLE pm_pairing_staff
  ADD PRIMARY KEY (tbl_id),
  ADD KEY pairing_id (pairing_id),
  ADD KEY staff_id (staff_id),
  ADD KEY event_id (event_id),
  ADD KEY invalid (invalid),
  ADD KEY discarded (discarded);

--
-- Indexes for table pm_reservation
--
ALTER TABLE pm_reservation
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY event_id_2 (event_id,pairing_id),
  ADD KEY slot_id (slot_id),
  ADD KEY event_id (event_id),
  ADD KEY day_id (day_id),
  ADD KEY pairing_id (pairing_id),
  ADD KEY phase_token (phase_token(5));

--
-- Indexes for table pm_setting
--
ALTER TABLE pm_setting
  ADD PRIMARY KEY (tbl_id),
  ADD UNIQUE KEY setting_key (setting_key(35),setting_year_starting);

--
-- Indexes for table pm_slot
--
ALTER TABLE pm_slot
  ADD PRIMARY KEY (id),
  ADD KEY event_id (event_id),
  ADD KEY day_id (day_id);

--
-- Indexes for table pm_staff_break
--
ALTER TABLE pm_staff_break
  ADD PRIMARY KEY (tbl_id),
  ADD KEY event_id (event_id),
  ADD KEY staff_id (staff_id),
  ADD KEY day_id (day_id),
  ADD KEY slot_id (slot_id);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table pm_day
--
ALTER TABLE pm_day
  MODIFY id smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table pm_event
--
ALTER TABLE pm_event
  MODIFY id smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table pm_event_setting
--
ALTER TABLE pm_event_setting
  MODIFY tbl_id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table pm_free_time
--
ALTER TABLE pm_free_time
  MODIFY tbl_id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table pm_pairing
--
ALTER TABLE pm_pairing
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table pm_pairing_staff
--
ALTER TABLE pm_pairing_staff
  MODIFY tbl_id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table pm_reservation
--
ALTER TABLE pm_reservation
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table pm_setting
--
ALTER TABLE pm_setting
  MODIFY tbl_id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table pm_slot
--
ALTER TABLE pm_slot
  MODIFY id smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table pm_staff_break
--
ALTER TABLE pm_staff_break
  MODIFY tbl_id int(11) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- Constraints for dumped tables
--

--
-- Constraints for table pm_day
--
ALTER TABLE pm_day
  ADD CONSTRAINT pm_day_ibfk_1 FOREIGN KEY (event_id) REFERENCES pm_event (id) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table pm_event_setting
--
ALTER TABLE pm_event_setting
  ADD CONSTRAINT pm_event_setting_ibfk_1 FOREIGN KEY (event_id) REFERENCES pm_event (id) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table pm_free_time
--
ALTER TABLE pm_free_time
  ADD CONSTRAINT pm_free_time_ibfk_1 FOREIGN KEY (event_id) REFERENCES pm_event (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT pm_free_time_ibfk_2 FOREIGN KEY (day_id) REFERENCES pm_day (id) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table pm_pairing
--
ALTER TABLE pm_pairing
  ADD CONSTRAINT pm_pairing_ibfk_1 FOREIGN KEY (event_id) REFERENCES pm_event (id) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT pm_pairing_ibfk_2 FOREIGN KEY (member_id) REFERENCES person (id) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table pm_pairing_staff
--
ALTER TABLE pm_pairing_staff
  ADD CONSTRAINT pm_pairing_staff_ibfk_2 FOREIGN KEY (staff_id) REFERENCES person (id),
  ADD CONSTRAINT pm_pairing_staff_ibfk_3 FOREIGN KEY (pairing_id) REFERENCES pm_pairing (id) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT pm_pairing_staff_ibfk_4 FOREIGN KEY (event_id) REFERENCES pm_event (id) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table pm_reservation
--
ALTER TABLE pm_reservation
  ADD CONSTRAINT pm_reservation_ibfk_1 FOREIGN KEY (event_id) REFERENCES pm_event (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT pm_reservation_ibfk_2 FOREIGN KEY (day_id) REFERENCES pm_day (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT pm_reservation_ibfk_3 FOREIGN KEY (slot_id) REFERENCES pm_slot (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT pm_reservation_ibfk_4 FOREIGN KEY (pairing_id) REFERENCES pm_pairing (id) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table pm_slot
--
ALTER TABLE pm_slot
  ADD CONSTRAINT pm_slot_ibfk_1 FOREIGN KEY (event_id) REFERENCES pm_event (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT pm_slot_ibfk_2 FOREIGN KEY (day_id) REFERENCES pm_day (id) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table pm_staff_break
--
ALTER TABLE pm_staff_break
  ADD CONSTRAINT pm_staff_break_ibfk_1 FOREIGN KEY (event_id) REFERENCES pm_event (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT pm_staff_break_ibfk_2 FOREIGN KEY (day_id) REFERENCES pm_day (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT pm_staff_break_ibfk_3 FOREIGN KEY (slot_id) REFERENCES pm_slot (id) ON DELETE CASCADE ON UPDATE CASCADE;
