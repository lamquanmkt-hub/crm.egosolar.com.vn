CAP NHAT MODULE NGHI PHEP - SALES_MANAGER

File da thay doi:
app/Services/Hr/LeaveApprovalAccessService.php

Noi dung:
- Them role sales_manager vao danh sach nguoi co the duoc chon lam nguoi duyet.
- Sales Manager co the duoc gan lam nguoi duyet cho nhan vien o moi phong ban.
- Sales Manager chi xem va duyet cac don duoc gan, khong duoc mo rong thanh quyen quan ly toan bo don nghi phep.
- Cac route, controller approve/reject va chuc nang chuyen nguoi duyet hien tai duoc giu nguyen.

Cach cap nhat:
1. Sao luu file app/Services/Hr/LeaveApprovalAccessService.php hien tai.
2. Ghi de file trong goi nay dung vao cung duong dan tren source.
3. Chay trong thu muc project:
   php artisan optimize:clear
4. Dang nhap lai, vao /nhan-su/leave-requests/create va kiem tra danh sach Nguoi duyet.

Quy uoc role dang su dung:
- Application guard: sales_manager
- Workflow/config (neu co): SALES_MANAGER
