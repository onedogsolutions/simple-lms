-- Find all wp_slms_user_course records where the user has a valid PMPro membership (i.e., pmpro_member = 1) 
-- but the course has been completed and a certificate exists in wp_slms_course_history.

SELECT 
    uc.user_id,
    uc.course_id,
    uc.enrolled_date,
    ch.course_name,
    ch.completed_date
FROM 
    wp_slms_user_course uc
INNER JOIN 
    wp_slms_course_history ch ON uc.user_id = ch.user_id AND uc.course_id = ch.course_name
WHERE 
    EXISTS (
        SELECT 1 FROM wp_pmpro_members m
        WHERE m.user_id = uc.user_id AND m.status = 'active'
    )
AND 
    ch.course_name IN (
        SELECT DISTINCT course_name FROM wp_slms_course_history WHERE user_id = uc.user_id
    );

-- To delete these ghost enrollments:
-- DELETE uc FROM wp_slms_user_course uc
-- INNER JOIN wp_slms_course_history ch ON uc.user_id = ch.user_id AND uc.course_id = ch.course_name
-- WHERE EXISTS (
--     SELECT 1 FROM wp_pmpro_members m
--     WHERE m.user_id = uc.user_id AND m.status = 'active'
-- );