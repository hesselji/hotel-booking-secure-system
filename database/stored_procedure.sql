USE luxebooking;

DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_create_secure_booking`$$

CREATE PROCEDURE `sp_create_secure_booking` (
    IN p_customer_id VARCHAR(36),
    IN p_room_unit_id VARCHAR(36),
    IN p_checkin DATE,
    IN p_checkout DATE,
    IN p_guests SMALLINT,
    OUT p_booking_id VARCHAR(36),
    OUT p_total_price DECIMAL(14,2),
    OUT p_status VARCHAR(50)
)
BEGIN
    DECLARE v_conflict INT DEFAULT 0;
    DECLARE v_price DECIMAL(12,2) DEFAULT 0;
    DECLARE v_nights SMALLINT DEFAULT 0;

    START TRANSACTION;

    SELECT r.price_per_night INTO v_price
    FROM room_units u
    JOIN rooms r ON u.room_id = r.id
    WHERE u.id = p_room_unit_id FOR UPDATE;

    SELECT COUNT(*) INTO v_conflict
    FROM bookings
    WHERE room_unit_id = p_room_unit_id
      AND payment_status IN ('pending', 'paid')
      AND p_checkin < checkout_date
      AND p_checkout > checkin_date;

    IF v_conflict > 0 THEN
        ROLLBACK;
        SET p_status = 'FAILED_ALREADY_BOOKED';
    ELSE
        SET p_booking_id = CONCAT('BK', UNIX_TIMESTAMP());
        SET v_nights = DATEDIFF(p_checkout, p_checkin);
        SET p_total_price = v_nights * v_price;

        INSERT INTO bookings (id, customer_id, room_unit_id, checkin_date, checkout_date, nights, guests, total_price, payment_status)
        VALUES (p_booking_id, p_customer_id, p_room_unit_id, p_checkin, p_checkout, v_nights, p_guests, p_total_price, 'pending');

        COMMIT;
        SET p_status = 'SUCCESS';
    END IF;
END$$

DELIMITER ;