<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CourseAudioController;
use App\Http\Controllers\CourseChapterController;
use App\Http\Controllers\CourseDocumentController;
use App\Http\Controllers\CourseImageController;
use App\Http\Controllers\GeoApiController;
use App\Http\Controllers\SubcategoryController;
use App\Http\Controllers\UserCreatorController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseSectionController;
use App\Http\Controllers\CourseVideoController;

Route::prefix('v1')->group(function () {

    // Categories
    Route::get('categories', [CategoryController::class, 'index']);
    Route::post('categories', [CategoryController::class, 'store']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);
    Route::put('categories/{category}', [CategoryController::class, 'update']);
    Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
    Route::post('categories/reorder', [CategoryController::class, 'reorder']);

    // Subcategories
    Route::get('subcategories', [SubcategoryController::class, 'index']);
    Route::get('categories/{category}/subcategories', [SubcategoryController::class, 'listByCategory']);
    Route::post('categories/{category}/subcategories', [SubcategoryController::class, 'store']);
    Route::post('categories/{category}/subcategories/reorder', [SubcategoryController::class, 'reorder']);
    Route::get('subcategories/{subcategory}', [SubcategoryController::class, 'show']);
    Route::put('subcategories/{subcategory}', [SubcategoryController::class, 'update']);
    Route::delete('subcategories/{subcategory}', [SubcategoryController::class, 'destroy']);

    // Courses
    Route::get('courses', [CourseController::class, 'index']);
    Route::post('courses', [CourseController::class, 'store']);
    Route::get('courses/{course}', [CourseController::class, 'show']);
    Route::put('courses/{course}', [CourseController::class, 'update']);
    Route::delete('courses/{course}', [CourseController::class, 'destroy']);

    Route::get('chapters', [CourseChapterController::class, 'index']);
    Route::post('chapters', [CourseChapterController::class, 'store']);
    Route::get('chapters/{chapter}', [CourseChapterController::class, 'show']);
    Route::put('chapters/{chapter}', [CourseChapterController::class, 'update']);
    Route::delete('chapters/{chapter}', [CourseChapterController::class, 'destroy']);

    // Sections (nested under course)
    Route::get('courses/{course}/sections', [CourseSectionController::class, 'index']);
    Route::post('courses/{course}/sections', [CourseSectionController::class, 'store']);
    Route::get('courses/{course}/sections/{section}', [CourseSectionController::class, 'show']);
    Route::put('courses/{course}/sections/{section}', [CourseSectionController::class, 'update']);
    Route::delete('courses/{course}/sections/{section}', [CourseSectionController::class, 'destroy']);
    Route::post('courses/{course}/sections/reorder', [CourseSectionController::class, 'reorder']);

    // Videos (nested under course, optional section_id)
    Route::get('courses/{course}/videos', [CourseVideoController::class, 'index']);
    Route::post('courses/{course}/videos', [CourseVideoController::class, 'store']);
    Route::get('courses/{course}/videos/{video}', [CourseVideoController::class, 'show']);
    Route::put('courses/{course}/videos/{video}', [CourseVideoController::class, 'update']);
    Route::delete('courses/{course}/videos/{video}', [CourseVideoController::class, 'destroy']);

    // Documents
    Route::get('courses/{course}/documents', [CourseDocumentController::class, 'index']);
    Route::post('courses/{course}/documents', [CourseDocumentController::class, 'store']);
    Route::get('courses/{course}/documents/{document}', [CourseDocumentController::class, 'show']);
    Route::put('courses/{course}/documents/{document}', [CourseDocumentController::class, 'update']);
    Route::delete('courses/{course}/documents/{document}', [CourseDocumentController::class, 'destroy']);

    // Audios
    Route::get('courses/{course}/audios', [CourseAudioController::class, 'index']);
    Route::post('courses/{course}/audios', [CourseAudioController::class, 'store']);
    Route::get('courses/{course}/audios/{audio}', [CourseAudioController::class, 'show']);
    Route::put('courses/{course}/audios/{audio}', [CourseAudioController::class, 'update']);
    Route::delete('courses/{course}/audios/{audio}', [CourseAudioController::class, 'destroy']);

    // Images
    Route::get('courses/{course}/images', [CourseImageController::class, 'index']);
    Route::post('courses/{course}/images', [CourseImageController::class, 'store']);
    Route::get('courses/{course}/images/{image}', [CourseImageController::class, 'show']);
    Route::put('courses/{course}/images/{image}', [CourseImageController::class, 'update']);
    Route::delete('courses/{course}/images/{image}', [CourseImageController::class, 'destroy']);

    // Create user + creator
    Route::post('users', [UserCreatorController::class, 'store']);

    // Read/update user & creator
    Route::get('users/{id}', [UserCreatorController::class, 'show']);
    Route::put('users/{id}', [UserCreatorController::class, 'update']);

    // Remove only the creator profile (keep the user)
    Route::delete('users/{id}/creator', [UserCreatorController::class, 'destroyCreator']);

    Route::prefix('geo')->group(function () {
        Route::get('/countries', [GeoApiController::class, 'countries']);   // list countries
        Route::get('/states', [GeoApiController::class, 'states']);      // by country
        Route::get('/cities', [GeoApiController::class, 'cities']);      // by state/country
        Route::get('/towns', [GeoApiController::class, 'towns']);       // by city/state/country
        Route::get('/search', [GeoApiController::class, 'search']);      // quick unified search

        Route::post('/countries', [GeoApiController::class, 'storeCountry']); // single or bulk
        Route::post('/states', [GeoApiController::class, 'storeState']);   // single or bulk
        Route::post('/cities', [GeoApiController::class, 'storeCity']);    // single or bulk
        Route::post('/towns', [GeoApiController::class, 'storeTown']);
    });
});

