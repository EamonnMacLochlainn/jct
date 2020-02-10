
USE $_ORG_DATABASE_NAME;

-- --------------------------------------------------------

CREATE TABLE `op_charge` (
    `id` int(10) UNSIGNED NOT NULL,
    `token` varchar(25) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
    `replaces` varchar(25) CHARACTER SET latin1 COLLATE latin1_bin DEFAULT NULL,
    `title` varchar(150) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    `description` varchar(500) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
    `type_id` tinyint(1) UNSIGNED NOT NULL,
    `applies_to_custom_group` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
    `category_id` tinyint(2) UNSIGNED NOT NULL DEFAULT '1',
    `year_starting` year(4) NOT NULL,
    `due_from` date NOT NULL,
    `due_to` date NOT NULL,
    `mandatory` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
    `amount_fixed` tinyint(1) UNSIGNED NOT NULL DEFAULT '1',
    `charge_status` enum('draft','open','closed') CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
    `affected_groups_saved` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
    `settings_saved` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
    `amounts_saved` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
    `cap_amount` decimal(6,2) UNSIGNED NOT NULL DEFAULT '0.00',
    `disregard_unaffected_members` tinyint(1) UNSIGNED NOT NULL DEFAULT '1',
    `closed` date DEFAULT NULL,
    `created` datetime NOT NULL,
    `replaced_by` int(10) UNSIGNED DEFAULT NULL,
    `updated` datetime NOT NULL,
    `updated_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `op_charge_category` (
    `id` tinyint(2) UNSIGNED NOT NULL,
    `title` varchar(50) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    `updated` datetime NOT NULL,
    `updated_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `op_charge_category` (`id`, `title`, `updated`, `updated_by`) VALUES
(1, 'General', '2019-03-22 18:35:20', -1);

CREATE TABLE `op_charge_history` (
    `tbl_id` int(10) UNSIGNED NOT NULL,
    `id` int(10) UNSIGNED NOT NULL,
    `charge_status` tinytext CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
    `updated` datetime NOT NULL,
    `updated_by` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `op_deposit` (
    `id` int(10) UNSIGNED NOT NULL,
    `account_id` int(10) UNSIGNED NOT NULL,
    `amount` decimal(8,2) UNSIGNED NOT NULL,
    `deposited` date NOT NULL,
    `deposit_comment` varchar(500) COLLATE utf8_bin DEFAULT NULL,
    `updated` datetime NOT NULL,
    `updated_by` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `op_deposit_account` (
    `id` int(10) UNSIGNED NOT NULL,
    `title` varchar(50) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
    `updated` datetime NOT NULL,
    `updated_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `op_fee` (
    `id` int(10) UNSIGNED NOT NULL,
    `member_id` int(10) UNSIGNED NOT NULL,
    `group_custom_id` tinyint(2) UNSIGNED NOT NULL DEFAULT '0',
    `group_class_id` int(10) UNSIGNED NOT NULL,
    `group_super_id` int(10) UNSIGNED NOT NULL,
    `charge_id` int(10) UNSIGNED NOT NULL,
    `amount_initial` decimal(6,2) UNSIGNED NOT NULL DEFAULT '0.00',
    `amount_due` decimal(6,2) UNSIGNED NOT NULL DEFAULT '0.00',
    `amount_paid` decimal(6,2) UNSIGNED NOT NULL DEFAULT '0.00',
    `stopped` datetime DEFAULT NULL,
    `created` datetime NOT NULL,
    `processing_fee_level` tinyint(3) UNSIGNED NOT NULL,
    `notification_email_sent` datetime DEFAULT NULL,
    `notification_text_sent` datetime DEFAULT NULL,
    `reminder_email_sent` datetime DEFAULT NULL,
    `reminder_text_sent` datetime DEFAULT NULL,
    `updated` datetime NOT NULL,
    `updated_by` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `op_fee_family_amount` (
    `tbl_id` int(10) UNSIGNED NOT NULL,
    `charge_id` int(10) UNSIGNED NOT NULL,
    `member_1` decimal(6,2) UNSIGNED NOT NULL DEFAULT '0.00',
    `member_2` decimal(6,2) UNSIGNED NOT NULL DEFAULT '0.00',
    `member_3` decimal(6,2) UNSIGNED NOT NULL DEFAULT '0.00',
    `member_4` decimal(6,2) UNSIGNED NOT NULL DEFAULT '0.00',
    `member_5` decimal(6,2) UNSIGNED NOT NULL DEFAULT '0.00',
    `member_6` decimal(6,2) UNSIGNED NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `op_fee_group_amount` (
    `tbl_id` int(10) UNSIGNED NOT NULL,
    `charge_id` int(10) UNSIGNED NOT NULL,
    `group_id` int(10) UNSIGNED NOT NULL,
    `amount` decimal(6,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `op_fee_group_payment_discount` (
    `tbl_id` int(10) UNSIGNED NOT NULL,
    `charge_id` int(10) UNSIGNED NOT NULL,
    `is_percent` tinyint(1) NOT NULL DEFAULT '0',
    `member_count` tinyint(1) NOT NULL DEFAULT '0',
    `amount` decimal(6,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `op_setting` (
    `tbl_id` int(10) UNSIGNED NOT NULL,
    `setting_key` tinytext NOT NULL,
    `setting_value` varchar(50) DEFAULT NULL,
    `setting_year_starting` year(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `op_transaction` (
    `id` int(10) UNSIGNED NOT NULL,
    `ext_token` varchar(255) DEFAULT NULL,
    `fee_level` tinyint(3) UNSIGNED NOT NULL,
    `method` enum('card','cash','cheque','credit','fee_decrease','fee_increase','refund') CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
    `amount` decimal(6,2) NOT NULL,
    `trx_comment` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
    `trx_comment_is_public` tinyint(1) NOT NULL DEFAULT '0',
    `user_id` int(10) UNSIGNED NOT NULL,
    `created` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `op_transaction_line_item` (
    `id` int(10) UNSIGNED NOT NULL,
    `refunded_by_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
    `transaction_id` int(10) UNSIGNED NOT NULL,
    `deposit_id` int(10) UNSIGNED DEFAULT '0',
    `fee_id` int(10) UNSIGNED NOT NULL,
    `member_id` int(10) UNSIGNED NOT NULL,
    `charge_id` int(10) UNSIGNED NOT NULL,
    `amount` decimal(6,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `op_user_ext_token` (
    `tbl_id` int(10) UNSIGNED NOT NULL,
    `id` int(10) UNSIGNED NOT NULL,
    `cust_token` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
    `card_token` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL,
    `active` tinyint(1) NOT NULL DEFAULT '0',
    `updated` datetime NOT NULL,
    `updated_by` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


ALTER TABLE `op_charge`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `title` (`title`),
  ADD UNIQUE KEY `token` (`token`),
  ADD UNIQUE KEY `replaces` (`replaces`),
  ADD KEY `type_id` (`type_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `year_starting` (`year_starting`),
  ADD KEY `stopped` (`closed`),
  ADD KEY `replaced_by` (`replaced_by`),
  ADD KEY `charge_status` (`charge_status`);

ALTER TABLE `op_charge_category`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `op_charge_history`
  ADD PRIMARY KEY (`tbl_id`),
  ADD UNIQUE KEY `id_2` (`id`,`charge_status`(50)),
  ADD KEY `id` (`id`);

ALTER TABLE `op_deposit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `updated_by` (`updated_by`);

ALTER TABLE `op_deposit_account`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `op_fee`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `member_charge` (`member_id`,`charge_id`) USING BTREE,
  ADD KEY `charge_id` (`charge_id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `group_class_id` (`group_class_id`),
  ADD KEY `group_super_id` (`group_super_id`),
  ADD KEY `cancelled` (`stopped`),
  ADD KEY `group_custom_id` (`group_custom_id`),
  ADD KEY `processing_fee_level` (`processing_fee_level`);

ALTER TABLE `op_fee_family_amount`
  ADD PRIMARY KEY (`tbl_id`),
  ADD UNIQUE KEY `charge_id` (`charge_id`) USING BTREE;

ALTER TABLE `op_fee_group_amount`
  ADD PRIMARY KEY (`tbl_id`),
  ADD KEY `charge_id` (`charge_id`);

ALTER TABLE `op_fee_group_payment_discount`
  ADD PRIMARY KEY (`tbl_id`),
  ADD UNIQUE KEY `charge_id` (`charge_id`);

ALTER TABLE `op_setting`
  ADD PRIMARY KEY (`tbl_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`(35),`setting_year_starting`);

ALTER TABLE `op_transaction`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `method` (`method`),
  ADD KEY `fee_level` (`fee_level`);

ALTER TABLE `op_transaction_line_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fee_id` (`fee_id`),
  ADD KEY `charge_id` (`charge_id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `transaction_id` (`transaction_id`);

ALTER TABLE `op_user_ext_token`
  ADD PRIMARY KEY (`tbl_id`),
  ADD KEY `id` (`id`),
  ADD KEY `active` (`active`);


ALTER TABLE `op_charge`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `op_charge_category`
  MODIFY `id` tinyint(2) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

ALTER TABLE `op_charge_history`
  MODIFY `tbl_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `op_deposit`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `op_deposit_account`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `op_fee`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `op_fee_family_amount`
  MODIFY `tbl_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `op_fee_group_amount`
  MODIFY `tbl_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `op_fee_group_payment_discount`
  MODIFY `tbl_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `op_setting`
  MODIFY `tbl_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `op_transaction`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `op_transaction_line_item`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `op_user_ext_token`
  MODIFY `tbl_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;


ALTER TABLE `op_charge`
  ADD CONSTRAINT `op_charge_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `op_charge_category` (`id`) ON UPDATE NO ACTION;

ALTER TABLE `op_charge_history`
  ADD CONSTRAINT `op_charge_history_ibfk_1` FOREIGN KEY (`id`) REFERENCES `op_charge` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

ALTER TABLE `op_deposit`
  ADD CONSTRAINT `op_deposit_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `op_deposit_account` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `op_deposit_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `person` (`id`) ON UPDATE CASCADE;

ALTER TABLE `op_fee`
  ADD CONSTRAINT `op_fee_ibfk_1` FOREIGN KEY (`charge_id`) REFERENCES `op_charge` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `op_fee_ibfk_2` FOREIGN KEY (`processing_fee_level`) REFERENCES `databizs_core`.`op_processing_fee_level` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `op_fee_ibfk_3` FOREIGN KEY (`member_id`) REFERENCES `person` (`id`) ON UPDATE CASCADE;

ALTER TABLE `op_fee_family_amount`
  ADD CONSTRAINT `op_fee_family_amount_ibfk_1` FOREIGN KEY (`charge_id`) REFERENCES `op_charge` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

ALTER TABLE `op_fee_group_amount`
  ADD CONSTRAINT `op_fee_group_amount_ibfk_1` FOREIGN KEY (`charge_id`) REFERENCES `op_charge` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

ALTER TABLE `op_fee_group_payment_discount`
  ADD CONSTRAINT `op_fee_group_payment_discount_ibfk_1` FOREIGN KEY (`charge_id`) REFERENCES `op_charge` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

ALTER TABLE `op_transaction_line_item`
  ADD CONSTRAINT `op_transaction_line_item_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `op_transaction` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `op_transaction_line_item_ibfk_2` FOREIGN KEY (`fee_id`) REFERENCES `op_fee` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
