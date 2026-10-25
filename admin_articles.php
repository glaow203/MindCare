
<script>
function addImage() {
    const list = document.getElementById('imageList');

    if (!list) {
        alert('ไม่พบส่วนแสดงช่องรูปภาพ กรุณาตรวจสอบ id="imageList"');
        return;
    }

    const row = document.createElement('div');
    row.className = 'image-row';

    const input = document.createElement('input');
    input.type = 'text';
    input.name = 'image_urls[]';
    input.placeholder = 'วาง URL รูปภาพที่นี่';

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'remove-image';
    button.textContent = 'ลบช่อง';
    button.addEventListener('click', function () {
        removeImage(button);
    });

    row.appendChild(input);
    row.appendChild(button);
    list.appendChild(row);
}

function removeImage(button) {
    const rows = document.querySelectorAll('#imageList .image-row');

    if (rows.length <= 1) {
        const input = button.parentElement.querySelector('input');
        if (input) input.value = '';
        return;
    }

    button.parentElement.remove();
}

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('articleForm');

    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            alert('แบบฟอร์มทำงานแล้ว แต่ยังไม่ได้บันทึกลง Supabase');
        });
    }
});
</script>
