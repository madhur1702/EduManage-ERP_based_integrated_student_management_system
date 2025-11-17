-- -----------------------------------------------------
-- Database: student_management_system
-- -----------------------------------------------------
USE student_management_system;
-- =====================================================
-- INSERT FEES FOR ALL STUDENTS (1 LAKH = 100,000 RUPEES)
-- =====================================================

INSERT INTO fees (student_id, total_fee, amount_paid, due_amount, last_payment_date)
SELECT 
    s.student_id,
    100000.00 as total_fee,
    0.00 as amount_paid,
    100000.00 as due_amount,
    NULL as last_payment_date
FROM students s
WHERE s.student_id NOT IN (
    SELECT DISTINCT student_id FROM fees
)
ORDER BY s.student_id;

-- =====================================================
-- VERIFICATION QUERY
-- =====================================================

SELECT 
    'Total Students with Fees' as Metric,
    COUNT(*) as Count
FROM fees
UNION ALL
SELECT 
    'Total Fees Amount',
    CONCAT('₹', FORMAT(SUM(total_fee), 0))
FROM fees
UNION ALL
SELECT 
    'Total Amount Due',
    CONCAT('₹', FORMAT(SUM(due_amount), 0))
FROM fees;

-- =====================================================
-- VIEW ALL STUDENT FEES
-- =====================================================

SELECT 
    s.student_id,
    s.roll_no,
    u.full_name,
    d.dept_name,
    s.admission_year,
    f.total_fee,
    f.amount_paid,
    f.due_amount,
    f.last_payment_date
FROM fees f
JOIN students s ON f.student_id = s.student_id
JOIN users u ON s.user_id = u.user_id
JOIN departments d ON u.dept_id = d.dept_id
ORDER BY s.admission_year DESC, d.dept_name, s.roll_no;

-- =====================================================
-- COUNT BY DEPARTMENT AND YEAR
-- =====================================================

SELECT 
    d.dept_name,
    s.admission_year,
    COUNT(f.fee_id) as Students_with_Fees,
    FORMAT(SUM(f.total_fee), 0) as Total_Fees_Amount,
    FORMAT(SUM(f.due_amount), 0) as Total_Amount_Due
FROM fees f
JOIN students s ON f.student_id = s.student_id
JOIN users u ON s.user_id = u.user_id
JOIN departments d ON u.dept_id = d.dept_id
GROUP BY d.dept_id, d.dept_name, s.admission_year
ORDER BY d.dept_name, s.admission_year DESC;