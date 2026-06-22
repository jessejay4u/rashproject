package com.healthplatform.utils

import android.content.Context
import androidx.hilt.work.HiltWorker
import androidx.work.*
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import com.healthplatform.data.local.OfflineSubmissionDao
import com.healthplatform.data.models.SubmissionRequest
import com.healthplatform.data.remote.ApiService
import dagger.assisted.Assisted
import dagger.assisted.AssistedInject
import kotlinx.coroutines.flow.first
import java.util.concurrent.TimeUnit

/**
 * Background worker that syncs queued offline submissions to the server.
 * Triggered automatically when network becomes available.
 */
@HiltWorker
class SyncWorker @AssistedInject constructor(
    @Assisted context: Context,
    @Assisted workerParams: WorkerParameters,
    private val apiService:           ApiService,
    private val offlineSubmissionDao: OfflineSubmissionDao,
    private val secureStorage:        SecureStorage
) : CoroutineWorker(context, workerParams) {

    private val gson = Gson()

    override suspend fun doWork(): Result {
        if (!secureStorage.isAuthenticated()) return Result.success()

        val pending = offlineSubmissionDao.getPending().first()
        if (pending.isEmpty()) return Result.success()

        var failed = 0

        for (submission in pending) {
            offlineSubmissionDao.updateStatus(submission.localId, "syncing")
            try {
                @Suppress("UNCHECKED_CAST")
                val values = gson.fromJson<Map<String, Any?>>(
                    submission.valuesJson,
                    object : TypeToken<Map<String, Any?>>() {}.type
                )

                val request = SubmissionRequest(
                    formId          = submission.formId,
                    hospitalId      = submission.hospitalId,
                    values          = values,
                    periodStart     = submission.periodStart,
                    periodEnd       = submission.periodEnd,
                    latitude        = submission.latitude,
                    longitude       = submission.longitude,
                    localId         = submission.localId,
                    durationSeconds = submission.durationSeconds
                )

                val response = apiService.submitData(request)
                if (response.isSuccessful) {
                    offlineSubmissionDao.updateStatus(submission.localId, "synced")
                } else {
                    offlineSubmissionDao.updateStatus(
                        submission.localId, "failed",
                        "Server error: ${response.code()}"
                    )
                    failed++
                }
            } catch (e: Exception) {
                offlineSubmissionDao.updateStatus(submission.localId, "pending", e.message)
                failed++
            }
        }

        return if (failed == 0) Result.success() else Result.retry()
    }

    companion object {
        const val WORK_NAME = "offline_sync"

        fun schedule(context: Context) {
            val constraints = Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .build()

            val request = PeriodicWorkRequestBuilder<SyncWorker>(15, TimeUnit.MINUTES)
                .setConstraints(constraints)
                .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 30, TimeUnit.SECONDS)
                .build()

            WorkManager.getInstance(context).enqueueUniquePeriodicWork(
                WORK_NAME,
                ExistingPeriodicWorkPolicy.KEEP,
                request
            )
        }

        fun runNow(context: Context) {
            val constraints = Constraints.Builder()
                .setRequiredNetworkType(NetworkType.CONNECTED)
                .build()

            val request = OneTimeWorkRequestBuilder<SyncWorker>()
                .setConstraints(constraints)
                .build()

            WorkManager.getInstance(context).enqueueUniqueWork(
                "${WORK_NAME}_immediate",
                ExistingWorkPolicy.REPLACE,
                request
            )
        }
    }
}
