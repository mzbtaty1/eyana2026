// Invoice Actions Handler - New Version
class InvoiceActionsNew {
    static approve(id) {
        const url = `${baseUrl}/invoices/${id}/approve`;
        
        $.ajax({
            type: "GET",
            url: url,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: { id: id },
            success: function(data) {
                const statusCode = data.status_code;
                
                if (statusCode === 200) {
                    InvoiceActionsNew.showSuccess("تم تأكيد العملية بنجاح");
                    InvoiceActionsNew.toggleApprovalButtons(id);
                } else {
                    InvoiceActionsNew.showError("فشل اثناء تأكيد العملية");
                }
            },
            error: function(xhr, status, error) {
                console.error("Error approving invoice:", error);
                InvoiceActionsNew.showError("حدث خطأ أثناء تأكيد العملية");
            }
        });
    }

    static toggleApprovalButtons(id) {
        const pendingBtn = document.getElementById(`rvd_new_${id}`);
        const approvedBtn = document.getElementById(`apprvd_new_${id}`);
        
        if (pendingBtn) pendingBtn.style.display = "none";
        if (approvedBtn) approvedBtn.style.display = "block";
    }

    static showSuccess(message) {
        if (typeof swal !== 'undefined') {
            swal("", message, "success");
        } else {
            alert(message);
        }
    }

    static showError(message) {
        if (typeof swal !== 'undefined') {
            swal("", message, "error");
        } else {
            alert(message);
        }
    }
}

// Global function for backward compatibility
function approveInvoiceNew(id) {
    InvoiceActionsNew.approve(id);
}