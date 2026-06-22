package com.healthplatform.data.local

import androidx.room.*
import kotlinx.coroutines.flow.Flow

// ── Entities ──────────────────────────────────────────────────────────────────

@Entity(tableName = "cached_forms")
data class CachedForm(
    @PrimaryKey val id: String,
    val name: String,
    val code: String,
    val description: String?,
    val category: String?,
    val version: Int,
    val jsonPayload: String,           // full form JSON for offline rendering
    val cachedAt: Long = System.currentTimeMillis()
)

@Entity(tableName = "offline_submissions")
data class OfflineSubmission(
    @PrimaryKey val localId: String,
    val formId: String,
    val hospitalId: String,
    val valuesJson: String,            // serialized Map<String, Any?>
    val periodStart: String?,
    val periodEnd: String?,
    val latitude: Double?,
    val longitude: Double?,
    val status: String = "pending",    // pending, syncing, synced, failed
    val errorMessage: String? = null,
    val durationSeconds: Int?,
    val createdAt: Long = System.currentTimeMillis(),
    val syncedAt: Long? = null
)

// ── DAOs ─────────────────────────────────────────────────────────────────────

@Dao
interface CachedFormDao {
    @Query("SELECT * FROM cached_forms ORDER BY name")
    fun getAll(): Flow<List<CachedForm>>

    @Query("SELECT * FROM cached_forms WHERE id = :id")
    suspend fun getById(id: String): CachedForm?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insert(form: CachedForm)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAll(forms: List<CachedForm>)

    @Query("DELETE FROM cached_forms WHERE cachedAt < :olderThan")
    suspend fun deleteOlderThan(olderThan: Long)
}

@Dao
interface OfflineSubmissionDao {
    @Query("SELECT * FROM offline_submissions WHERE status = 'pending' ORDER BY createdAt")
    fun getPending(): Flow<List<OfflineSubmission>>

    @Query("SELECT * FROM offline_submissions ORDER BY createdAt DESC")
    fun getAll(): Flow<List<OfflineSubmission>>

    @Query("SELECT COUNT(*) FROM offline_submissions WHERE status = 'pending'")
    fun getPendingCount(): Flow<Int>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insert(submission: OfflineSubmission)

    @Update
    suspend fun update(submission: OfflineSubmission)

    @Query("DELETE FROM offline_submissions WHERE status = 'synced' AND syncedAt < :olderThan")
    suspend fun deleteSyncedOlderThan(olderThan: Long)

    @Query("UPDATE offline_submissions SET status = :status, errorMessage = :error WHERE localId = :id")
    suspend fun updateStatus(id: String, status: String, error: String? = null)
}

// ── Database ──────────────────────────────────────────────────────────────────

@Database(
    entities = [CachedForm::class, OfflineSubmission::class],
    version = 1,
    exportSchema = false
)
abstract class AppDatabase : RoomDatabase() {
    abstract fun cachedFormDao(): CachedFormDao
    abstract fun offlineSubmissionDao(): OfflineSubmissionDao
}
