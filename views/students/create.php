<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Add New Student";

include '../layouts/header.php';
?>

<div class="px-4 sm:px-6 lg:px-8 max-w-3xl mx-auto">
    <!-- Breadcrumb -->
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-sm">
            <li class="inline-flex items-center">
                <a href="<?php url('views/dashboard/index.php'); ?>"
                    class="inline-flex items-center text-slate-500 hover:text-slate-700">
                    Home
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m1 9 4-4-4-4" />
                    </svg>
                    <a href="<?php url('views/students/index.php'); ?>"
                        class="ml-1 text-slate-500 hover:text-slate-700">Students</a>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m1 9 4-4-4-4" />
                    </svg>
                    <span class="ml-1 font-medium text-slate-800">Add New</span>
                </div>
            </li>
        </ol>
    </nav>

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900">Add New Student</h1>
        <p class="mt-2 text-sm text-slate-600">Enroll a new student into the system.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl md:col-span-2">
        <form action="<?php url('logic/students/store.php'); ?>" method="POST">
            <div class="px-4 py-6 sm:p-8">
                <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">

                    <div class="sm:col-span-4">
                        <label for="name" class="block text-sm font-medium leading-6 text-gray-900">Student Name</label>
                        <div class="mt-2">
                            <input type="text" name="name" id="name" required
                                class="block w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                placeholder="e.g. Emma Thompson">
                        </div>
                    </div>

                    <div class="sm:col-span-4">
                        <label for="class_grade" class="block text-sm font-medium leading-6 text-gray-900">Class /
                            Grade</label>
                        <div class="mt-2">
                            <input type="text" name="class_grade" id="class_grade" required
                                class="block w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-700 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none transition-all placeholder:text-slate-400 shadow-sm"
                                placeholder="e.g. Grade 10-A">
                        </div>
                    </div>

                </div>
            </div>
            <div class="flex items-center justify-end gap-x-6 border-t border-gray-900/10 px-4 py-4 sm:px-8">
                <a href="<?php url('views/students/index.php'); ?>"
                    class="text-sm font-semibold leading-6 text-gray-900">Cancel</a>
                <button type="submit"
                    class="rounded-md bg-cyan-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 transition-colors">
                    Save Student
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>