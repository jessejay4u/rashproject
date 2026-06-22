package com.healthplatform.data.remote

import com.healthplatform.data.models.*
import retrofit2.Response
import retrofit2.http.*

interface ApiService {

    // ── Auth ──────────────────────────────────────────────────────────────────
    @POST("/api/auth/login")
    suspend fun login(@Body request: LoginRequest): Response<ApiResponse<AuthResponse>>

    @POST("/api/auth/refresh")
    suspend fun refreshToken(@Body body: Map<String, String>): Response<ApiResponse<AuthResponse>>

    @POST("/api/auth/logout")
    suspend fun logout(@Body body: Map<String, String>): Response<ApiResponse<Unit>>

    @GET("/api/auth/me")
    suspend fun getMe(): Response<ApiResponse<User>>

    @POST("/api/auth/change-password")
    suspend fun changePassword(@Body body: Map<String, String>): Response<ApiResponse<Unit>>

    @POST("/api/auth/forgot-password")
    suspend fun forgotPassword(@Body body: Map<String, String>): Response<ApiResponse<Unit>>

    // ── Forms ─────────────────────────────────────────────────────────────────
    @GET("/api/forms")
    suspend fun getForms(
        @Query("status")   status: String = "published",
        @Query("page")     page: Int = 1,
        @Query("per_page") perPage: Int = 50
    ): Response<PaginatedResponse<Form>>

    @GET("/api/forms/{id}")
    suspend fun getForm(@Path("id") id: String): Response<ApiResponse<Form>>

    // ── Submissions ───────────────────────────────────────────────────────────
    @POST("/api/submissions")
    suspend fun submitData(@Body request: SubmissionRequest): Response<ApiResponse<Map<String, String>>>

    @GET("/api/submissions")
    suspend fun getSubmissions(
        @Query("page")     page: Int = 1,
        @Query("per_page") perPage: Int = 20,
        @Query("status")   status: String? = null,
        @Query("form_id")  formId: String? = null
    ): Response<PaginatedResponse<Submission>>

    @GET("/api/submissions/{id}")
    suspend fun getSubmission(@Path("id") id: String): Response<ApiResponse<Submission>>

    @POST("/api/submissions/sync")
    suspend fun syncBatch(@Body body: Map<String, List<SubmissionRequest>>): Response<ApiResponse<Map<String, Any>>>

    // ── Dashboard ─────────────────────────────────────────────────────────────
    @GET("/api/dashboard/summary")
    suspend fun getDashboardSummary(): Response<ApiResponse<Map<String, Any>>>

    // ── Hospitals ─────────────────────────────────────────────────────────────
    @GET("/api/hospitals")
    suspend fun getHospitals(
        @Query("page")     page: Int = 1,
        @Query("per_page") perPage: Int = 100
    ): Response<PaginatedResponse<Map<String, Any>>>
}
