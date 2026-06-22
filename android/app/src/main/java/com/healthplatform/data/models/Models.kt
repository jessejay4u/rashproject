package com.healthplatform.data.models

import com.google.gson.annotations.SerializedName

// ── Auth ──────────────────────────────────────────────────────────────────────

data class LoginRequest(
    val email: String,
    val password: String,
    @SerializedName("device_info") val deviceInfo: DeviceInfo
)

data class DeviceInfo(
    @SerializedName("device_id")  val deviceId: String,
    @SerializedName("user_agent") val userAgent: String,
    val platform: String = "android"
)

data class AuthResponse(
    @SerializedName("access_token")  val accessToken: String,
    @SerializedName("refresh_token") val refreshToken: String,
    @SerializedName("token_type")    val tokenType: String,
    @SerializedName("expires_in")    val expiresIn: Int,
    val user: User,
    @SerializedName("mfa_required")  val mfaRequired: Boolean = false
)

data class User(
    val id: String,
    val name: String,
    val email: String,
    val phone: String?,
    @SerializedName("role_name")    val roleName: String,
    @SerializedName("hospital_id")  val hospitalId: String?,
    @SerializedName("region_id")    val regionId: String?,
    @SerializedName("hospital_name") val hospitalName: String?,
    @SerializedName("is_active")    val isActive: Boolean,
    @SerializedName("mfa_enabled")  val mfaEnabled: Boolean
)

// ── Forms ─────────────────────────────────────────────────────────────────────

data class Form(
    val id: String,
    val name: String,
    val code: String,
    val description: String?,
    val category: String?,
    val version: Int,
    val status: String,
    val settings: Map<String, Any>?,
    val sections: List<FormSection>?,
    @SerializedName("field_count") val fieldCount: Int = 0
)

data class FormSection(
    val id: String,
    val title: String,
    val description: String?,
    @SerializedName("order_index") val orderIndex: Int,
    @SerializedName("is_repeatable") val isRepeatable: Boolean,
    val fields: List<FormField>
)

data class FormField(
    val id: String,
    val name: String,
    val label: String,
    @SerializedName("field_type")  val fieldType: String,
    @SerializedName("is_required") val isRequired: Boolean,
    @SerializedName("order_index") val orderIndex: Int,
    val placeholder: String?,
    @SerializedName("help_text")   val helpText: String?,
    @SerializedName("default_value") val defaultValue: String?,
    val options: List<FieldOption>?,
    val validation: FieldValidation?,
    val conditions: List<FieldCondition>?
)

data class FieldOption(val value: String, val label: String)

data class FieldValidation(
    val min: Double?,
    val max: Double?,
    val minLength: Int?,
    val maxLength: Int?,
    val pattern: String?
)

data class FieldCondition(
    val field: String,
    val operator: String,
    val value: String,
    val action: String   // show, hide, require
)

// ── Submissions ───────────────────────────────────────────────────────────────

data class SubmissionRequest(
    @SerializedName("form_id")          val formId: String,
    @SerializedName("hospital_id")      val hospitalId: String,
    val values: Map<String, Any?>,
    val status: String = "submitted",
    @SerializedName("period_start")     val periodStart: String? = null,
    @SerializedName("period_end")       val periodEnd: String? = null,
    val latitude: Double? = null,
    val longitude: Double? = null,
    @SerializedName("local_id")         val localId: String,
    @SerializedName("device_info")      val deviceInfo: DeviceInfo? = null,
    @SerializedName("duration_seconds") val durationSeconds: Int? = null
)

data class Submission(
    val id: String,
    @SerializedName("form_id")       val formId: String,
    @SerializedName("form_name")     val formName: String,
    @SerializedName("hospital_id")   val hospitalId: String,
    @SerializedName("hospital_name") val hospitalName: String,
    val status: String,
    @SerializedName("submitted_at")  val submittedAt: String?,
    @SerializedName("period_start")  val periodStart: String?,
    @SerializedName("period_end")    val periodEnd: String?,
    @SerializedName("submitted_by_name") val submittedByName: String
)

// ── Generic API response ──────────────────────────────────────────────────────

data class ApiResponse<T>(
    val success: Boolean,
    val message: String,
    val data: T?,
    val errors: Map<String, List<String>>?
)

data class PaginatedResponse<T>(
    val success: Boolean,
    val message: String,
    val data: List<T>,
    val meta: PaginationMeta
)

data class PaginationMeta(
    val total: Int,
    @SerializedName("per_page")     val perPage: Int,
    @SerializedName("current_page") val currentPage: Int,
    @SerializedName("last_page")    val lastPage: Int
)
