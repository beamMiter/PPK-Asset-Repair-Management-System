{{--
  The two ways to add a file, as bare icons: a paperclip (pick a file) and a camera (take a photo). No box, no border, no background —
  just the icon, with a soft circle on hover (the `ghost` variant at `icon-lg`, the look of the icons in the chat header). They are
  still real <button>s, so the keyboard and screen readers work, and each says what it is on hover (title) and to a screen reader
  (aria-label). Put them wherever a file can be added, so every page looks the same — in the `actions` slot of the section's heading,
  its top right corner (the corner where the assign-team icon sits on the job page). The ids are the ones the page's script binds.

  <x-ui.section-head no="4" title="ไฟล์แนบ" subtitle="...">
      <x-slot:actions><x-ui.attach-buttons any="mr_files_any_btn" camera="mr_files_camera_btn" /></x-slot:actions>
  </x-ui.section-head>
  <x-ui.attach-buttons any="hero_image_any_btn" camera="hero_image_camera_btn" any-label="เลือกรูปภาพ" />

  Renders the two buttons only (no wrapper); the heading's slot lays them out in a row.
--}}
@props([
    'any',
    'camera',
    'anyLabel' => 'แนบไฟล์',
    'cameraLabel' => 'ถ่ายรูป',
])

<x-ui.button :id="$any" variant="ghost" size="icon-lg" icon="attach_file" :aria-label="$anyLabel" :title="$anyLabel" />
<x-ui.button :id="$camera" variant="ghost" size="icon-lg" icon="photo_camera" :aria-label="$cameraLabel" :title="$cameraLabel" />
