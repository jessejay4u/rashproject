package com.healthplatform.viewmodels

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.healthplatform.data.local.OfflineSubmissionDao
import com.healthplatform.data.remote.ApiService
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch
import javax.inject.Inject

@HiltViewModel
class DashboardViewModel @Inject constructor(
    private val apiService:           ApiService,
    private val offlineSubmissionDao: OfflineSubmissionDao
) : ViewModel() {

    private val _summary   = MutableStateFlow<Map<String, Any>?>(null)
    val summary: StateFlow<Map<String, Any>?> = _summary

    private val _isLoading = MutableStateFlow(false)
    val isLoading: StateFlow<Boolean> = _isLoading

    val pendingOfflineCount: StateFlow<Int> = offlineSubmissionDao
        .getPendingCount()
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5000), 0)

    fun loadSummary() {
        viewModelScope.launch {
            _isLoading.value = true
            try {
                val response = apiService.getDashboardSummary()
                if (response.isSuccessful) {
                    @Suppress("UNCHECKED_CAST")
                    _summary.value = response.body()?.data as? Map<String, Any>
                }
            } catch (e: Exception) {
                // Fail silently — dashboard can show cached/empty state
            } finally {
                _isLoading.value = false
            }
        }
    }
}
