{{--
  The two ways to add a file, as icon-only square buttons: a paperclip (pick a file) and a camera (take a photo). Icons are enough —
  each says what it is on hover (title) and to a screen reader (aria-label) — and they leave the row for what matters. Put them
  wherever a file can be added, so every page looks the same. The ids are the ones the page's script binds.

  <x-ui.attach-buttons any="mr_files_any_btn" camera="mr_files_camera_btn" />
  <x-ui.attach-buttons any="hero_image_any_btn" camera="hero_image_camera_btn" any-label="เลือกรูปภาพ" />

  Renders the two buttons only (no wrapper): put them in your own flex row with whatever else belongs beside them.
--}}
@props([
    'any',
    'camera',
    'anyLabel' => 'แนบไฟล์',
    'cameraLabel' => 'ถ่ายรูป',
])

<x-ui.button :id="$any" size="square" icon="attach_file" :aria-label="$anyLabel" :title="$anyLabel" />
<x-ui.button :id="$camera" size="square" icon="photo_camera" :aria-label="$cameraLabel" :title="$cameraLabel" />
