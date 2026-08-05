<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title', 'PAIRfect Paws Admin'); ?></title>

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/admin.css', 'resources/js/admin.js']); ?>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

    <div class="topbar max-[991px]:flex hidden">
        <button class="topbar-toggle" id="sidebarToggle" aria-label="Open menu">
            <i class="fa-solid fa-bars"></i>
        </button>
        <span class="topbar-title">PAIRfect Paws Admin</span>
    </div>

    <div class="app-container">

        <?php echo $__env->make('admin.partials.sidebar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <div class="main-content">
            <?php echo $__env->yieldContent('content'); ?>
        </div>

    </div>

    <div id="toastHost"></div>

    <?php if(session('toast')): ?>
        <script>
            window.addEventListener('DOMContentLoaded', function () {
                window.PAIRfectAdmin?.showToast(<?php echo json_encode(session('toast.message'), 15, 512) ?>, <?php echo json_encode(session('toast.type', 'success'), 512) ?>);
            });
        </script>
    <?php endif; ?>

    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\Users\Jessa\Downloads\pairfect-paws-web (2)\pairfect-paws-web\resources\views/admin/layouts/app.blade.php ENDPATH**/ ?>