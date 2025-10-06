// =============================
// EXPENSES MODULE JAVASCRIPT
// Handles: submission, approval, and history pages
// =============================

// --- 1. EXPENSE SUBMISSION PAGE ---
document.addEventListener('DOMContentLoaded', function() {
    const expenseForm = document.getElementById('expenseForm');
    if (expenseForm) {
        expenseForm.addEventListener('submit', function(e) {
            const title = this.querySelector('[name="title"]').value.trim();
            const reason = this.querySelector('[name="reason"]').value.trim();
            const amount = this.querySelector('[name="amount"]').value.trim();

            if (!title || !reason || !amount) {
                e.preventDefault();
                alert("Please fill in all fields (Title, Reason, Amount).");
                return;
            }

            if (isNaN(amount) || parseFloat(amount) <= 0) {
                e.preventDefault();
                alert("Please enter a valid amount.");
                return;
            }
        });
    }
});

// --- 2. PASTOR APPROVAL PAGE ---
function approveExpense(expenseId) {
    if (confirm("Are you sure you want to APPROVE this expense?")) {
        sendApprovalRequest(expenseId, "Approved");
    }
}

function declineExpense(expenseId) {
    if (confirm("Are you sure you want to DECLINE this expense?")) {
        sendApprovalRequest(expenseId, "Declined");
    }
}

function sendApprovalRequest(expenseId, action) {
    fetch("process_expense.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `expense_id=${expenseId}&action=${action}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Expense ${action} successfully!`);
            location.reload();
        } else {
            alert("Failed to update expense status.");
        }
    })
    .catch(error => {
        console.error("Error:", error);
        alert("An error occurred while processing the request.");
    });
}

// --- 3. APPROVED/DECLINED EXPENSES PAGE ---
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    if (filterButtons.length > 0) {
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                const status = this.dataset.status;
                filterExpenses(status);
            });
        });
    }
});

function filterExpenses(status) {
    const rows = document.querySelectorAll('.expenses-table tbody tr');
    rows.forEach(row => {
        if (status === "All" || row.dataset.status === status) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
}
