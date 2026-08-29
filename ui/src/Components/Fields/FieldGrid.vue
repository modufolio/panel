<template>
  <div class="ui-field-grid grid grid-cols-12 gap-x-4 gap-y-5">
    <slot />
  </div>
</template>

<!--
  Usage: Wrap field components inside <FieldGrid>. Each field's `width` prop
  controls how many columns it spans. On mobile, all fields are full-width.

  <FieldGrid>
    <TextField label="First name" width="1/2" />
    <TextField label="Last name" width="1/2" />
    <SelectField label="Country" width="1/3" />
    <TextField label="City" width="2/3" />
    <TextareaField label="Bio" />
  </FieldGrid>

  width values → col-span:
    1/4  → col-span-3
    1/3  → col-span-4
    1/2  → col-span-6
    2/3  → col-span-8
    3/4  → col-span-9
    full → col-span-12  (default)
-->

<script setup lang="ts">
// No props — layout is driven by child field `width` props
</script>
